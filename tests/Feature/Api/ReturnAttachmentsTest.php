<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Webkul\RMA\Models\RMA;
use Webkul\RMA\Models\RMAMessage;
use Webkul\RMA\Models\RMAStatus;

beforeEach(function () {
    Storage::fake();
    Mail::fake();

    RMAStatus::firstOrCreate(['id' => 1], ['title' => 'Pending', 'status' => 1, 'default' => 1]);
});

const CREATE_RETURN = <<<'GQL'
    mutation CreateReturn($input: createCustomerReturnInput!) {
      createCustomerReturn(input: $input) {
        customerReturn { _id images }
      }
    }
GQL;

const SEND_MESSAGE = <<<'GQL'
    mutation SendMessage($input: createCustomerReturnMessageInput!) {
      createCustomerReturnMessage(input: $input) {
        customerReturnMessage { _id message attachment attachmentUrl }
      }
    }
GQL;

function returnInput(array $seed): array
{
    return [
        'orderId' => $seed['order']->id,
        'orderItemId' => $seed['orderItem']->id,
        'rmaQty' => 1,
        'resolutionType' => 'return',
        'rmaReasonId' => 1,
        'information' => 'Arrived cracked',
        'agreement' => true,
    ];
}

// ====================================================================
// Schema
// ====================================================================

it('exposes the Upload scalar and the upload fields', function () {
    $response = $this->graphQl('{
        upload: __type(name: "Upload") { kind }
        returnInput: __type(name: "createCustomerReturnInput") { inputFields { name type { kind ofType { name } } } }
        messageInput: __type(name: "createCustomerReturnMessageInput") { inputFields { name type { name } } }
    }');

    $response->assertOk();

    expect($response->json('data.upload.kind'))->toBe('SCALAR');

    $returnFields = collect($response->json('data.returnInput.inputFields'))->keyBy('name');

    expect($returnFields['images']['type']['ofType']['name'])->toBe('Upload')
        ->and($returnFields->keys()->all())->toContain('orderId', 'orderItemId', 'rmaQty', 'agreement');

    $messageFields = collect($response->json('data.messageInput.inputFields'))->keyBy('name');

    expect($messageFields['file']['type']['name'])->toBe('Upload')
        ->and($messageFields->keys()->all())->toContain('returnId', 'message');
});

// ====================================================================
// Return Images
// ====================================================================

it('creates a return with the uploaded images', function () {
    $customer = $this->createCustomer();
    $seed = $this->createReturnableOrder($customer);

    $response = $this->graphQlUpload(CREATE_RETURN, ['input' => returnInput($seed) + ['images' => [null, null]]], [
        'input.images.0' => UploadedFile::fake()->image('front.jpg'),
        'input.images.1' => UploadedFile::fake()->image('back.png'),
    ], $customer);

    $response->assertOk();

    expect($response->json('errors'))->toBeNull();

    $images = $response->json('data.createCustomerReturn.customerReturn.images');
    $rma = RMA::where('order_id', $seed['order']->id)->firstOrFail();

    expect($images)->toHaveCount(2)
        ->and($rma->images)->toHaveCount(2)
        ->and($images[0]['url'])->toContain('rmas/'.$rma->id.'/images/');

    foreach ($rma->images as $image) {
        Storage::assertExists($image->path);
    }
});

it('still creates a return without images', function () {
    $customer = $this->createCustomer();
    $seed = $this->createReturnableOrder($customer);

    $response = $this->graphQl(CREATE_RETURN, ['input' => returnInput($seed)], $customer);

    $response->assertOk();

    expect($response->json('errors'))->toBeNull()
        ->and($response->json('data.createCustomerReturn.customerReturn._id'))->not->toBeNull();
});

it('rejects a file type the store does not allow, without creating the return', function () {
    $customer = $this->createCustomer();
    $seed = $this->createReturnableOrder($customer);

    $response = $this->graphQlUpload(CREATE_RETURN, ['input' => returnInput($seed) + ['images' => [null]]], [
        'input.images.0' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
    ], $customer);

    expect($response->json('errors'))->not->toBeNull()
        ->and(RMA::where('order_id', $seed['order']->id)->exists())->toBeFalse();
});

// ====================================================================
// Message Attachments
// ====================================================================

it('sends a return message with an attached file', function () {
    $customer = $this->createCustomer();
    $seed = $this->createReturnableOrder($customer);
    $returnId = $this->graphQl(CREATE_RETURN, ['input' => returnInput($seed)], $customer)->json('data.createCustomerReturn.customerReturn._id');

    $response = $this->graphQlUpload(SEND_MESSAGE, ['input' => ['returnId' => (int) $returnId, 'message' => 'Photo of the box', 'file' => null]], [
        'input.file' => UploadedFile::fake()->image('box.jpg'),
    ], $customer);

    $response->assertOk();

    expect($response->json('errors'))->toBeNull();

    $message = RMAMessage::findOrFail($response->json('data.createCustomerReturnMessage.customerReturnMessage._id'));

    expect($message->message)->toBe('Photo of the box')
        ->and($message->attachment)->toBe('box.jpg')
        ->and($response->json('data.createCustomerReturnMessage.customerReturnMessage.attachmentUrl'))->toContain('rma-conversation/'.$message->id);

    Storage::assertExists($message->attachment_path);
});

it('sends a message carrying only a file', function () {
    $customer = $this->createCustomer();
    $seed = $this->createReturnableOrder($customer);
    $returnId = $this->graphQl(CREATE_RETURN, ['input' => returnInput($seed)], $customer)->json('data.createCustomerReturn.customerReturn._id');

    $response = $this->graphQlUpload(SEND_MESSAGE, ['input' => ['returnId' => (int) $returnId, 'file' => null]], [
        'input.file' => UploadedFile::fake()->image('label.png'),
    ], $customer);

    expect($response->json('errors'))->toBeNull()
        ->and($response->json('data.createCustomerReturnMessage.customerReturnMessage.attachment'))->toBe('label.png');
});

it('still sends a message without a file', function () {
    $customer = $this->createCustomer();
    $seed = $this->createReturnableOrder($customer);
    $returnId = $this->graphQl(CREATE_RETURN, ['input' => returnInput($seed)], $customer)->json('data.createCustomerReturn.customerReturn._id');

    $response = $this->graphQl(SEND_MESSAGE, ['input' => ['returnId' => (int) $returnId, 'message' => 'Any update?']], $customer);

    expect($response->json('errors'))->toBeNull()
        ->and($response->json('data.createCustomerReturnMessage.customerReturnMessage.message'))->toBe('Any update?');
});

it('will not attach a file to another customer\'s return', function () {
    $owner = $this->createCustomer();
    $seed = $this->createReturnableOrder($owner);
    $returnId = $this->graphQl(CREATE_RETURN, ['input' => returnInput($seed)], $owner)->json('data.createCustomerReturn.customerReturn._id');

    $response = $this->graphQlUpload(SEND_MESSAGE, ['input' => ['returnId' => (int) $returnId, 'message' => 'Hi', 'file' => null]], [
        'input.file' => UploadedFile::fake()->image('x.jpg'),
    ], $this->createCustomer());

    expect($response->json('errors'))->not->toBeNull()
        ->and(RMAMessage::where('rma_id', $returnId)->where('message', 'Hi')->exists())->toBeFalse();
});

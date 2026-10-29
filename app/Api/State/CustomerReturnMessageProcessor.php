<?php

namespace App\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Symfony\Component\Mime\MimeTypes;
use Webkul\Admin\Mail\Admin\RMA\CustomerToAdminConversationNotification;
use Webkul\BagistoApi\Dto\SendCustomerReturnMessageInput;
use Webkul\BagistoApi\Exception\AuthorizationException;
use Webkul\BagistoApi\Exception\InvalidInputException;
use Webkul\BagistoApi\Exception\ResourceNotFoundException;
use Webkul\BagistoApi\Models\CustomerReturnMessage;
use Webkul\BagistoApi\State\CustomerReturnMessageProcessor as BaseCustomerReturnMessageProcessor;
use Webkul\RMA\Repositories\RMAMessageRepository;
use Webkul\RMA\Repositories\RMARepository;

/**
 * Sends a return message with the file uploaded alongside it, leaving messages without a file to the package.
 */
class CustomerReturnMessageProcessor implements ProcessorInterface
{
    /**
     * The largest file accepted, in kilobytes.
     */
    public const MAX_FILE_KILOBYTES = 10240;

    /**
     * Create a new processor around the package's return message processor.
     */
    public function __construct(
        protected BaseCustomerReturnMessageProcessor $customerReturnMessageProcessor,
        protected RMARepository $rmaRepository,
        protected RMAMessageRepository $rmaMessageRepository,
    ) {}

    /**
     * Send the message, storing its file before the admin is notified.
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $file = $this->uploadedFile($context);

        if (
            ! $data instanceof SendCustomerReturnMessageInput
            || $file === null
        ) {
            return $this->customerReturnMessageProcessor->process($data, $operation, $uriVariables, $context);
        }

        $customer = Auth::guard('sanctum')->user();

        if (! $customer) {
            throw new AuthorizationException(__('bagistoapi::app.graphql.logout.unauthenticated'));
        }

        if (! $data->return_id) {
            throw new InvalidInputException(__('bagistoapi::app.graphql.return.invalid-input'));
        }

        $rma = $this->rmaRepository
            ->whereHas('order', fn ($query) => $query->where('customer_id', $customer->id))
            ->find($data->return_id);

        if (! $rma) {
            throw new ResourceNotFoundException(__('bagistoapi::app.graphql.return.not-found'));
        }

        $this->validateFile($file);

        $stored = $this->rmaMessageRepository->create([
            'rma_id' => $rma->id,
            'message' => (string) $data->message,
            'is_admin' => 0,
        ]);

        $this->rmaMessageRepository->update([
            'attachment_path' => $file->storeAs('rma-conversation/'.$stored->id, $this->fileName($file)),
            'attachment' => $file->getClientOriginalName(),
        ], $stored->id);

        try {
            Mail::queue(new CustomerToAdminConversationNotification($stored->refresh()));
        } catch (\Exception) {
        }

        return CustomerReturnMessage::fromModel($stored->refresh());
    }

    /**
     * The file sent in the mutation's file argument, as a Laravel upload, or null when none was sent.
     *
     * The GraphQL entrypoint passes on the Symfony upload it reads from the multipart request.
     */
    protected function uploadedFile(array $context): mixed
    {
        $file = $context['args']['input']['file'] ?? null;

        return $file instanceof SymfonyUploadedFile && ! $file instanceof UploadedFile
            ? UploadedFile::createFromBase($file)
            : $file;
    }

    /**
     * Reject the file unless it is an allowed type and within the size limit.
     */
    protected function validateFile(mixed $file): void
    {
        $validator = Validator::make(['file' => $file], [
            'file' => 'file|max:'.self::MAX_FILE_KILOBYTES.'|mimetypes:'.core()->getConfigData('sales.rma.setting.allowed_file_extension'),
        ]);

        if ($validator->fails()) {
            throw new InvalidInputException($validator->errors()->first());
        }
    }

    /**
     * A random file name keeping the extension of the file's detected type.
     */
    protected function fileName(UploadedFile $file): string
    {
        $extension = MimeTypes::getDefault()->getExtensions((string) $file->getMimeType())[0] ?? null;

        return Str::random(40).($extension ? '.'.$extension : '');
    }
}

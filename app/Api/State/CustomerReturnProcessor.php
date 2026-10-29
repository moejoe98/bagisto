<?php

namespace App\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Webkul\BagistoApi\Dto\CreateCustomerReturnInput;
use Webkul\BagistoApi\Exception\InvalidInputException;
use Webkul\BagistoApi\Models\CustomerReturn;
use Webkul\BagistoApi\State\Concerns\BuildsCustomerReturn;
use Webkul\BagistoApi\State\CustomerReturnProcessor as BaseCustomerReturnProcessor;
use Webkul\RMA\Repositories\RMAImageRepository;
use Webkul\RMA\Repositories\RMARepository;

/**
 * Creates a return through the package's processor, then stores the images or videos uploaded with it.
 */
class CustomerReturnProcessor implements ProcessorInterface
{
    use BuildsCustomerReturn;

    /**
     * The most files one return can carry.
     */
    public const MAX_FILES = 10;

    /**
     * The largest file accepted, in kilobytes.
     */
    public const MAX_FILE_KILOBYTES = 10240;

    /**
     * Create a new processor around the package's return processor.
     */
    public function __construct(
        protected BaseCustomerReturnProcessor $customerReturnProcessor,
        protected RMARepository $rmaRepository,
        protected RMAImageRepository $rmaImageRepository,
    ) {}

    /**
     * Create the return and attach the uploaded files.
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $files = $this->uploadedFiles($context);

        if (
            ! $data instanceof CreateCustomerReturnInput
            || empty($files)
        ) {
            return $this->customerReturnProcessor->process($data, $operation, $uriVariables, $context);
        }

        $this->validateFiles($files);

        $customerReturn = $this->customerReturnProcessor->process($data, $operation, $uriVariables, $context);

        if (! $customerReturn instanceof CustomerReturn) {
            return $customerReturn;
        }

        foreach ($files as $file) {
            $this->rmaImageRepository->create([
                'rma_id' => $customerReturn->id,
                'path' => $file->store('rmas/'.$customerReturn->id.'/images'),
            ]);
        }

        $rma = $this->rmaRepository->with(self::RETURN_RELATIONS)->find($customerReturn->id);

        return $this->buildCustomerReturn($rma, true, $this->rmaRepository);
    }

    /**
     * The files sent in the mutation's images argument, as Laravel uploads.
     *
     * The GraphQL entrypoint passes on the Symfony uploads it reads from the multipart request.
     *
     * @return array<int, mixed>
     */
    protected function uploadedFiles(array $context): array
    {
        $images = array_values(array_filter((array) ($context['args']['input']['images'] ?? [])));

        return array_map(
            fn ($image) => $image instanceof SymfonyUploadedFile && ! $image instanceof UploadedFile
                ? UploadedFile::createFromBase($image)
                : $image,
            $images
        );
    }

    /**
     * Reject the upload unless every file is an allowed type and within the size and count limits.
     *
     * @param  array<int, UploadedFile>  $files
     */
    protected function validateFiles(array $files): void
    {
        $validator = Validator::make(['images' => $files], [
            'images' => 'array|max:'.self::MAX_FILES,
            'images.*' => 'file|max:'.self::MAX_FILE_KILOBYTES.'|mimetypes:'.core()->getConfigData('sales.rma.setting.allowed_file_extension'),
        ]);

        if ($validator->fails()) {
            throw new InvalidInputException($validator->errors()->first());
        }
    }
}

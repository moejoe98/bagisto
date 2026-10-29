<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Webkul\CMS\Repositories\PageRepository;
use Webkul\Core\Repositories\CoreConfigRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Theme\Enums\SectionTypeEnum;
use Webkul\Theme\Repositories\SectionRepository;

class ApplyStoreContent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'its-apple:store-content {--force : Run without asking for confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply the Its Apple store content: homepage carousels, CMS pages and checkout settings';

    /**
     * The content definition read from database/content/store.php.
     */
    protected array $content = [];

    /**
     * Create a new command instance.
     */
    public function __construct(
        protected SectionRepository $sectionRepository,
        protected PageRepository $pageRepository,
        protected ProductRepository $productRepository,
        protected CoreConfigRepository $coreConfigRepository,
    ) {
        parent::__construct();
    }

    /**
     * Apply every part of the store content. Each step is safe to run again.
     */
    public function handle(): int
    {
        $this->content = require base_path('database/content/store.php');

        if (
            ! $this->option('force')
            && ! $this->confirm('This will update the homepage, CMS pages and payment methods of the database "'.config('database.connections.'.config('database.default').'.database').'". Continue?')
        ) {
            return self::FAILURE;
        }

        $this->components->task('Homepage banner carousel', fn () => $this->applyBanners());
        $this->components->task('Category and product carousels', fn () => $this->applyCarousels());
        $this->components->task('Web-only hero and category grid switched off', fn () => $this->switchOffReplacedSections());
        $this->components->task('CMS pages', fn () => $this->applyPages());
        $this->components->task('Payment methods', fn () => $this->applyPaymentMethods());
        $this->components->task('Leftover test product disabled', fn () => $this->disableLeftoverProducts());

        $this->call('responsecache:clear');

        return self::SUCCESS;
    }

    /**
     * Fill the image carousel with the Its Apple banners and switch it on, at the top of the homepage.
     */
    protected function applyBanners(): void
    {
        $section = $this->sectionRepository->getModel()->newQuery()
            ->where('type', SectionTypeEnum::IMAGE_CAROUSEL->value)
            ->where('channel_id', core()->getDefaultChannel()->id)
            ->orderBy('id')
            ->first();

        $images = [];

        foreach ($this->content['banners'] as $banner) {
            $file = new UploadedFile(base_path('database/content/banners/'.$banner['file'].'.webp'), $banner['file'].'.webp', 'image/webp', null, true);

            $images[] = [
                'image' => $this->sectionRepository->storeImage($section->id, $file),
                'title' => $banner['title'],
                'link' => $banner['link'],
            ];
        }

        foreach ($section->translations as $translation) {
            $translation->options = ['images' => $images];
            $translation->draft_options = null;
            $translation->save();
        }

        $section->update(['status' => 1, 'draft_status' => null, 'sort_order' => 1]);
    }

    /**
     * Create the category and product carousels the mobile app renders natively, unless they already exist.
     */
    protected function applyCarousels(): void
    {
        foreach ($this->content['carousels'] as $carousel) {
            $existing = $this->sectionRepository->getModel()->newQuery()
                ->where('type', $carousel['type'])
                ->where('name', $carousel['name'])
                ->first();

            if ($existing) {
                continue;
            }

            $this->sectionRepository->create([
                'type' => $carousel['type'],
                'name' => $carousel['name'],
                'sort_order' => $carousel['sort_order'],
                'status' => 1,
                'channel_id' => core()->getDefaultChannel()->id,
                'theme_code' => core()->getDefaultChannel()->theme,
                'en' => ['options' => $carousel['options']],
            ]);
        }
    }

    /**
     * Switch off the web-style hero and category grid that the carousels replace.
     */
    protected function switchOffReplacedSections(): void
    {
        $ids = $this->sectionRepository->getModel()->newQuery()
            ->where('type', SectionTypeEnum::STATIC_CONTENT->value)
            ->whereIn('name', $this->content['replaced_static_sections'])
            ->pluck('id')
            ->all();

        $this->sectionRepository->massUpdateStatus(['status' => 0], $ids);
    }

    /**
     * Replace the placeholder text of the remaining CMS pages.
     *
     * The URL keys stay the same, so the before-update event, which records URL redirects from the admin form, is not fired.
     */
    protected function applyPages(): void
    {
        foreach ($this->content['pages'] as $urlKey => $page) {
            $existing = $this->pageRepository->findByUrlKey($urlKey);

            if (! $existing) {
                continue;
            }

            $html = str_replace(['{support_email}', '{date}'], [$this->content['support_email'], now()->format('j F Y')], $page['html']);

            $updated = $this->pageRepository->update([
                'locale' => 'en',
                'channels' => [core()->getDefaultChannel()->id],
                'en' => [
                    'url_key' => $urlKey,
                    'page_title' => $page['title'],
                    'html_content' => '<div class="static-container"><div class="mb-5">'.$html.'</div></div>',
                    'meta_title' => $page['title'],
                    'meta_description' => '',
                    'meta_keywords' => '',
                ],
            ], $existing->id);

            Event::dispatch('cms.page.update.after', $updated);
        }
    }

    /**
     * Leave only the payment methods the store has set up, as the admin configuration screen would save them.
     */
    protected function applyPaymentMethods(): void
    {
        $methods = [];

        foreach ($this->content['payment_methods']['enabled'] as $code) {
            $methods[$code] = ['active' => 1];
        }

        foreach ($this->content['payment_methods']['disabled'] as $code) {
            $methods[$code] = ['active' => 0];
        }

        $this->coreConfigRepository->create([
            'channel' => core()->getDefaultChannelCode(),
            'locale' => 'en',
            'sales' => ['payment_methods' => $methods],
        ]);
    }

    /**
     * Disable the leftover test products, as the admin's mass status update does, so nothing else about them changes.
     */
    protected function disableLeftoverProducts(): void
    {
        foreach ($this->content['leftover_product_skus'] as $sku) {
            $product = $this->productRepository->findOneByField('sku', $sku);

            if (! $product) {
                continue;
            }

            Event::dispatch('catalog.product.update.before', $product->id);

            $product = $this->productRepository->update(['status' => 0], $product->id, ['status']);

            Event::dispatch('catalog.product.update.after', $product);
        }
    }
}

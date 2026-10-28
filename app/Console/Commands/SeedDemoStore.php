<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\CartRule\Repositories\CartRuleRepository;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\CMS\Repositories\PageRepository;
use Webkul\Core\Repositories\SubscribersListRepository;
use Webkul\Customer\Repositories\CustomerAddressRepository;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Customer\Repositories\WishlistRepository;
use Webkul\Marketing\Repositories\SearchTermRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Product\Repositories\ProductReviewRepository;
use Webkul\RMA\Repositories\RMAItemRepository;
use Webkul\RMA\Repositories\RMAMessageRepository;
use Webkul\RMA\Repositories\RMARepository;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\RefundRepository;
use Webkul\Sales\Repositories\ShipmentRepository;
use Webkul\Sales\Transformers\OrderResource;

class SeedDemoStore extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'its-apple:demo-store
        {--purge : Remove the demo data instead of seeding it}
        {--force : Run without asking for confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed a complete Its Apple demo store (catalog, customers, orders, returns, reviews) or purge it';

    /**
     * The lifecycle each demo order goes through, one entry per order.
     */
    public const ORDER_SCENARIOS = [
        'completed', 'completed', 'completed', 'completed', 'completed', 'completed', 'completed',
        'completed', 'completed', 'completed', 'completed', 'processing', 'processing', 'processing',
        'processing', 'processing', 'pending', 'pending', 'pending', 'pending', 'canceled', 'canceled',
        'canceled', 'refunded', 'refunded', 'partially_refunded', 'unpaid', 'unpaid',
    ];

    /**
     * The RMA status each demo return ends in: pending, approved, awaiting return, declined.
     */
    public const RETURN_STATUSES = [1, 2, 3, 7];

    /**
     * The demo data definition read from database/demo/store.php.
     */
    protected array $demo = [];

    /**
     * Seeded categories keyed by slug.
     */
    protected array $categories = [];

    /**
     * Seeded products keyed by URL key.
     */
    protected array $products = [];

    /**
     * Seeded customers, each with its default address.
     */
    protected array $customers = [];

    /**
     * Seeded orders that reached the completed state without a refund.
     */
    protected array $completedOrders = [];

    /**
     * Create a new command instance.
     */
    public function __construct(
        protected AttributeRepository $attributeRepository,
        protected CategoryRepository $categoryRepository,
        protected ProductRepository $productRepository,
        protected ProductReviewRepository $productReviewRepository,
        protected CustomerRepository $customerRepository,
        protected CustomerAddressRepository $customerAddressRepository,
        protected WishlistRepository $wishlistRepository,
        protected SubscribersListRepository $subscribersListRepository,
        protected CartRepository $cartRepository,
        protected CartRuleRepository $cartRuleRepository,
        protected OrderRepository $orderRepository,
        protected InvoiceRepository $invoiceRepository,
        protected ShipmentRepository $shipmentRepository,
        protected RefundRepository $refundRepository,
        protected RMARepository $rmaRepository,
        protected RMAItemRepository $rmaItemRepository,
        protected RMAMessageRepository $rmaMessageRepository,
        protected SearchTermRepository $searchTermRepository,
        protected PageRepository $pageRepository,
    ) {
        parent::__construct();
    }

    /**
     * Seed or purge the demo store.
     */
    public function handle(): int
    {
        $this->demo = require base_path('database/demo/store.php');

        $action = $this->option('purge') ? 'remove the Its Apple demo data from' : 'add the Its Apple demo data to';

        if (
            ! $this->option('force')
            && ! $this->confirm('This will '.$action.' the database "'.config('database.connections.'.config('database.default').'.database').'". Continue?')
        ) {
            return self::FAILURE;
        }

        config(['mail.default' => 'array', 'broadcasting.default' => 'null']);

        if ($this->option('purge')) {
            $this->purge();
        } else {
            if ($this->demoProductIds()->isNotEmpty()) {
                $this->error('The demo store is already seeded. Run with --purge first to seed it again.');

                return self::FAILURE;
            }

            $this->seed();
        }

        $this->call('indexer:index', ['--mode' => ['full']]);
        $this->call('responsecache:clear');

        return self::SUCCESS;
    }

    /**
     * Seed every part of the demo store, in dependency order.
     */
    protected function seed(): void
    {
        mt_srand(2026);

        $this->components->task('Categories', fn () => $this->seedCategories());
        $this->components->task('Products and images', fn () => $this->seedProducts());
        $this->components->task('Customers, addresses and newsletter', fn () => $this->seedCustomers());
        $this->components->task('Coupon '.$this->demo['coupon']['code'], fn () => $this->seedCoupon());
        $this->components->task('Orders, invoices, shipments and refunds', fn () => $this->seedOrders());
        $this->components->task('Returns', fn () => $this->seedReturns());
        $this->components->task('Reviews', fn () => $this->seedReviews());
        $this->components->task('Wishlists and abandoned carts', fn () => $this->seedWishlistsAndCarts());
        $this->components->task('Search terms', fn () => $this->seedSearchTerms());
        $this->components->task('CMS pages', fn () => $this->updatePages());
        $this->components->task('Homepage category links', fn () => $this->linkHomepageCategories());

        Carbon::setTestNow();

        $this->newLine();
        $this->info('Demo store seeded. Demo customers sign in with any @'.$this->demo['email_domain'].' email and the password '.$this->demo['customer_password'].', e.g. '.$this->customers[0]['customer']->email.'.');
    }

    /**
     * Create the demo categories under the channel's root category, each with a logo.
     */
    protected function seedCategories(): void
    {
        $rootId = core()->getDefaultChannel()->root_category_id;
        $priceAttributeId = $this->attributeRepository->findOneByField('code', 'price')->id;
        $position = 1;

        foreach ($this->demo['categories'] as $slug => $definition) {
            $category = $this->categoryRepository->findBySlug($slug);

            if (! $category) {
                Event::dispatch('catalog.category.create.before');

                $category = $this->categoryRepository->create([
                    'locale' => 'all',
                    'name' => $definition['name'],
                    'slug' => $slug,
                    'description' => '<p>'.$definition['description'].'</p>',
                    'meta_title' => $definition['name'],
                    'meta_keywords' => '',
                    'meta_description' => $definition['description'],
                    'status' => 1,
                    'position' => $position,
                    'display_mode' => 'products_and_description',
                    'parent_id' => $rootId,
                    'attributes' => [$priceAttributeId],
                ]);

                $logoPath = 'categories/'.$category->id.'/logo.webp';

                Storage::put($logoPath, file_get_contents($this->imagePath($definition['logo'])));

                $this->categoryRepository->getModel()->newQuery()->whereKey($category->id)->update(['logo_path' => $logoPath]);

                Event::dispatch('catalog.category.create.after', $category);
            }

            $this->categories[$slug] = $category;
            $position++;
        }
    }

    /**
     * Create the demo products with their prices, stock, category and image.
     */
    protected function seedProducts(): void
    {
        foreach ($this->demo['products'] as $index => $definition) {
            $sku = $this->demo['sku_prefix'].strtoupper(Str::slug($definition['url_key']));

            $product = $this->productRepository->create([
                'type' => 'simple',
                'attribute_family_id' => 1,
                'sku' => $sku,
            ]);

            Event::dispatch('catalog.product.update.before', $product->id);

            $product = $this->productRepository->update([
                'channel' => core()->getDefaultChannelCode(),
                'locale' => 'en',
                'sku' => $sku,
                'product_number' => '',
                'name' => $definition['name'],
                'url_key' => $definition['url_key'],
                'short_description' => '<p>'.$definition['short'].'</p>',
                'description' => '<p>'.$definition['short'].'</p><ul><li>Designed for everyday use</li><li>Tested by the Its Apple team</li><li>1-year warranty</li></ul>',
                'meta_title' => $definition['name'],
                'meta_keywords' => '',
                'meta_description' => $definition['short'],
                'price' => $definition['price'],
                'cost' => round($definition['price'] * 0.45, 2),
                'special_price' => $definition['special_price'] ?? null,
                'special_price_from' => null,
                'special_price_to' => null,
                'weight' => 0.2,
                'length' => '',
                'width' => '',
                'height' => '',
                'status' => 1,
                'visible_individually' => 1,
                'new' => $definition['new'],
                'featured' => $definition['featured'],
                'guest_checkout' => 1,
                'manage_stock' => 1,
                'allow_rma' => 1,
                'tax_category_id' => '',
                'channels' => [core()->getDefaultChannel()->id],
                'categories' => [$this->categories[$definition['category']]->id],
                'inventories' => [1 => $index % 9 === 4 ? 9 : mt_rand(60, 160)],
                'images' => ['files' => [new UploadedFile($this->imagePath($definition['url_key']), $definition['url_key'].'.webp', 'image/webp', null, true)]],
            ], $product->id);

            Event::dispatch('catalog.product.update.after', $product);

            $this->products[$definition['url_key']] = $product;
        }
    }

    /**
     * Create the demo customers with a default address, subscribing some to the newsletter.
     */
    protected function seedCustomers(): void
    {
        $startedAt = now();

        foreach ($this->demo['customers'] as $index => $definition) {
            Carbon::setTestNow($startedAt->copy()->subDays(150 - $index * 9));

            $email = Str::lower($definition['first_name'].'.'.$definition['last_name']).'@'.$this->demo['email_domain'];

            $customer = $this->customerRepository->create([
                'first_name' => $definition['first_name'],
                'last_name' => $definition['last_name'],
                'gender' => $definition['gender'],
                'email' => $email,
                'phone' => '+97150'.str_pad((string) (1000000 + $index * 13579), 7, '0', STR_PAD_LEFT),
                'date_of_birth' => null,
                'password' => bcrypt($this->demo['customer_password']),
                'customer_group_id' => 2,
                'channel_id' => core()->getDefaultChannel()->id,
                'is_verified' => 1,
                'status' => 1,
                'subscribed_to_news_letter' => $definition['newsletter'],
            ]);

            $address = $this->customerAddressRepository->create([
                'customer_id' => $customer->id,
                'company_name' => null,
                'vat_id' => null,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'address' => ($index + 12).' Al Wasl Road, Building '.($index + 3),
                'city' => $definition['city'],
                'state' => $definition['state'],
                'country' => 'AE',
                'postcode' => '00000',
                'phone' => $customer->phone,
                'email' => $customer->email,
                'default_address' => 1,
            ]);

            if ($definition['newsletter']) {
                $this->subscribersListRepository->create([
                    'email' => $customer->email,
                    'is_subscribed' => 1,
                    'token' => Str::random(64),
                    'customer_id' => $customer->id,
                    'channel_id' => core()->getDefaultChannel()->id,
                ]);
            }

            $this->customers[] = ['customer' => $customer, 'address' => $address];
        }

        Carbon::setTestNow();
    }

    /**
     * Create the percentage coupon some demo orders use.
     */
    protected function seedCoupon(): void
    {
        $coupon = $this->demo['coupon'];

        if ($this->cartRuleRepository->findOneByField('name', $coupon['name'])) {
            return;
        }

        Event::dispatch('promotions.cart_rule.create.before');

        $rule = $this->cartRuleRepository->create([
            'name' => $coupon['name'],
            'description' => $coupon['discount'].'% off with the code '.$coupon['code'],
            'status' => 1,
            'channels' => [core()->getDefaultChannel()->id],
            'customer_groups' => [1, 2, 3],
            'coupon_type' => 1,
            'use_auto_generation' => 0,
            'coupon_code' => $coupon['code'],
            'uses_per_coupon' => 0,
            'usage_per_customer' => 0,
            'starts_from' => null,
            'ends_till' => null,
            'condition_type' => 1,
            'conditions' => [],
            'action_type' => 'by_percent',
            'discount_amount' => $coupon['discount'],
            'discount_quantity' => 0,
            'discount_step' => 0,
            'apply_to_shipping' => 0,
            'free_shipping' => 0,
            'end_other_rules' => 0,
            'sort_order' => 0,
        ]);

        Event::dispatch('promotions.cart_rule.create.after', $rule);
    }

    /**
     * Place the demo orders through the checkout, then invoice, ship, refund or cancel them over the following days.
     */
    protected function seedOrders(): void
    {
        $scenarios = self::ORDER_SCENARIOS;
        $startedAt = now();

        $this->shuffle($scenarios);

        foreach ($scenarios as $index => $scenario) {
            $placedAt = $startedAt->copy()->subDays(58 - $index * 2)->setTime(mt_rand(9, 22), mt_rand(0, 59));

            $order = $this->placeOrder($this->customers[$index % count($this->customers)], $placedAt, $index % 4 === 1);

            $this->advanceOrder($order, $scenario, $placedAt);
        }

        Carbon::setTestNow();
    }

    /**
     * Place one order for the customer at the given time, through the same cart and checkout calls the storefront makes.
     */
    protected function placeOrder(array $buyer, Carbon $placedAt, bool $withCoupon)
    {
        Carbon::setTestNow($placedAt);

        Auth::guard('customer')->setUser($buyer['customer']);

        Cart::createCart(['customer' => $buyer['customer'], 'is_active' => true]);

        foreach ($this->pickProducts(mt_rand(1, 3)) as $product) {
            Cart::addProduct($this->productRepository->find($product->id), [
                'product_id' => $product->id,
                'quantity' => mt_rand(1, 2),
            ]);
        }

        Cart::saveAddresses(['billing' => $this->checkoutAddress($buyer)]);
        Cart::collectTotals();

        Cart::saveShippingMethod(mt_rand(0, 2) ? 'flatrate_flatrate' : 'free_free');
        Cart::collectTotals();

        Cart::savePaymentMethod(['method' => mt_rand(0, 1) ? 'cashondelivery' : 'moneytransfer']);
        Cart::collectTotals();

        if ($withCoupon) {
            Cart::setCouponCode($this->demo['coupon']['code'])->collectTotals();
        }

        $order = $this->orderRepository->create((new OrderResource(Cart::getCart()))->jsonSerialize());

        Cart::deActivateCart();
        Cart::resetCart();

        Auth::guard('customer')->forgetUser();

        return $order;
    }

    /**
     * Take the order through its scenario: invoice, ship, refund or cancel, a day or two apart.
     */
    protected function advanceOrder($order, string $scenario, Carbon $placedAt): void
    {
        if ($scenario === 'pending') {
            return;
        }

        if ($scenario === 'canceled') {
            Carbon::setTestNow($placedAt->copy()->addHours(5));

            $this->orderRepository->cancel($order->id, true);

            return;
        }

        Carbon::setTestNow($placedAt->copy()->addDay());

        $this->invoiceRepository->create([
            'order_id' => $order->id,
            'invoice' => ['items' => $this->orderItemQuantities($order)],
        ], $scenario === 'unpaid' ? 'pending' : 'paid');

        if (in_array($scenario, ['processing', 'unpaid'])) {
            return;
        }

        Carbon::setTestNow($placedAt->copy()->addDays(2));

        $this->shipmentRepository->create([
            'order_id' => $order->id,
            'shipment' => [
                'carrier_title' => 'Aramex',
                'track_number' => 'AX'.mt_rand(100000000, 999999999),
                'source' => 1,
                'items' => collect($this->orderItemQuantities($order))->map(fn ($qty) => [1 => $qty])->all(),
            ],
        ]);

        if ($scenario === 'completed') {
            $this->completedOrders[] = $this->orderRepository->find($order->id);

            return;
        }

        Carbon::setTestNow($placedAt->copy()->addDays(6));

        $order = $this->orderRepository->find($order->id);
        $quantities = $this->orderItemQuantities($order);

        if ($scenario === 'partially_refunded') {
            $quantities = array_slice($quantities, 0, 1, true);
            $quantities[array_key_first($quantities)] = 1;
        }

        $this->refundRepository->create([
            'order_id' => $order->id,
            'refund' => [
                'items' => $quantities,
                'shipping' => $scenario === 'refunded' ? (float) $order->base_shipping_invoiced : 0,
                'adjustment_refund' => $scenario === 'partially_refunded' ? 3 : 0,
                'adjustment_fee' => $scenario === 'refunded' ? 2 : 0,
            ],
        ]);
    }

    /**
     * Open returns on completed orders, one in each of the demo return statuses, with a short conversation.
     */
    protected function seedReturns(): void
    {
        foreach (self::RETURN_STATUSES as $index => $statusId) {
            $order = $this->completedOrders[$index] ?? null;

            if (! $order) {
                return;
            }

            $item = $order->items->first();

            Carbon::setTestNow($order->created_at->copy()->addDays(5));

            Event::dispatch('sales.rma.request.create.before', []);

            $rma = $this->rmaRepository->create([
                'order_id' => $order->id,
                'rma_status_id' => $statusId,
                'information' => 'The item arrived with a scratch on the front.',
                'package_condition' => $index % 2 ? 'open' : 'packed',
            ]);

            $this->rmaItemRepository->create([
                'rma_id' => $rma->id,
                'rma_reason_id' => $index + 1,
                'order_item_id' => $item->id,
                'variant_id' => null,
                'quantity' => 1,
                'resolution' => 'return',
            ]);

            $this->rmaMessageRepository->create(['rma_id' => $rma->id, 'message' => 'Hi, the item I received is scratched. Can I get a replacement?', 'is_admin' => 0]);

            if ($statusId !== 1) {
                Carbon::setTestNow($order->created_at->copy()->addDays(6));

                $this->rmaMessageRepository->create([
                    'rma_id' => $rma->id,
                    'message' => $statusId === 7 ? 'Sorry, this item is outside the return window.' : 'Thanks for letting us know. Please send the item back in its original box.',
                    'is_admin' => 1,
                ]);
            }

            Event::dispatch('sales.rma.request.create.after', $rma);
        }
    }

    /**
     * Leave reviews from the demo customers on the products, a few still waiting for approval.
     */
    protected function seedReviews(): void
    {
        $count = 0;
        $startedAt = now();

        foreach ($this->products as $product) {
            foreach (array_slice($this->customers, mt_rand(0, 6), mt_rand(0, 3)) as $buyer) {
                [$rating, $title, $comment] = $this->demo['reviews'][$count % count($this->demo['reviews'])];

                Carbon::setTestNow($startedAt->copy()->subDays(mt_rand(1, 45)));

                Event::dispatch('customer.review.create.before', $product->id);

                $review = $this->productReviewRepository->create([
                    'product_id' => $product->id,
                    'customer_id' => $buyer['customer']->id,
                    'name' => $buyer['customer']->first_name.' '.$buyer['customer']->last_name,
                    'title' => $title,
                    'comment' => $comment,
                    'rating' => $rating,
                    'status' => ++$count % 7 === 0 ? 'pending' : 'approved',
                ]);

                Carbon::setTestNow();

                Event::dispatch('customer.review.create.after', $review);
            }
        }
    }

    /**
     * Fill some wishlists and leave a few carts behind, so the abandoned cart report has entries.
     */
    protected function seedWishlistsAndCarts(): void
    {
        foreach (array_slice($this->customers, 0, 5) as $buyer) {
            foreach ($this->pickProducts(mt_rand(2, 3)) as $product) {
                $this->wishlistRepository->create([
                    'channel_id' => core()->getDefaultChannel()->id,
                    'product_id' => $product->id,
                    'customer_id' => $buyer['customer']->id,
                ]);
            }
        }

        $startedAt = now();

        foreach (array_slice($this->customers, 7) as $index => $buyer) {
            Carbon::setTestNow($startedAt->copy()->subDays(4 + $index * 2));

            Auth::guard('customer')->setUser($buyer['customer']);

            Cart::createCart(['customer' => $buyer['customer'], 'is_active' => true]);

            foreach ($this->pickProducts(2) as $product) {
                Cart::addProduct($this->productRepository->find($product->id), ['product_id' => $product->id, 'quantity' => 1]);
            }

            Cart::collectTotals();
            Cart::resetCart();

            Auth::guard('customer')->forgetUser();
            Carbon::setTestNow();
        }
    }

    /**
     * Record the searches shoppers made, for the search terms report.
     */
    protected function seedSearchTerms(): void
    {
        foreach ($this->demo['search_terms'] as $term => $uses) {
            $this->searchTermRepository->updateOrCreate([
                'term' => $term,
                'channel_id' => core()->getDefaultChannel()->id,
                'locale' => 'en',
            ], [
                'results' => mt_rand(1, 8),
                'uses' => $uses,
                'display_in_suggested_terms' => 0,
            ]);
        }
    }

    /**
     * Replace the placeholder text of the store's CMS pages with Its Apple content.
     *
     * The URL keys stay the same, so the before-update event, which records URL redirects from the admin form, is not fired.
     */
    protected function updatePages(): void
    {
        foreach ($this->demo['pages'] as $urlKey => $page) {
            $existing = $this->pageRepository->findByUrlKey($urlKey);

            if (! $existing) {
                continue;
            }

            $updated = $this->pageRepository->update([
                'locale' => 'en',
                'channels' => [core()->getDefaultChannel()->id],
                'en' => [
                    'url_key' => $urlKey,
                    'page_title' => $page['title'],
                    'html_content' => '<div class="static-container"><div class="mb-5">'.$page['html'].'</div></div>',
                    'meta_title' => $page['title'],
                    'meta_description' => '',
                    'meta_keywords' => '',
                ],
            ], $existing->id);

            Event::dispatch('cms.page.update.after', $updated);
        }
    }

    /**
     * Point the homepage's category tiles and hero buttons at the new category pages instead of search results.
     */
    protected function linkHomepageCategories(): void
    {
        $links = [
            'search?query=case"' => 'cases-covers"',
            'search?query=charger"' => 'chargers-cables"',
            'search?query=screen protector"' => 'screen-protectors"',
            'search?query=airpods"' => 'airpods-audio"',
            'search?query=power bank"' => 'power-banks"',
            'search?query=watch band"' => 'watch-bands"',
        ];

        DB::table('theme_section_translations')->orderBy('id')->each(function ($translation) use ($links) {
            $options = json_decode($translation->options, true);

            if (! isset($options['html'])) {
                return;
            }

            $html = str_replace(array_keys($links), array_values($links), $options['html']);

            if ($html !== $options['html']) {
                $options['html'] = $html;

                DB::table('theme_section_translations')->where('id', $translation->id)->update([
                    'options' => json_encode($options, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]);
            }
        });
    }

    /**
     * Remove every demo record: orders and carts of the demo customers, the customers, products, categories, coupon and search terms.
     */
    protected function purge(): void
    {
        $domain = '%@'.$this->demo['email_domain'];

        $this->components->task('Orders, invoices, shipments, refunds and returns', function () use ($domain) {
            foreach ($this->orderRepository->getModel()->newQuery()->where('customer_email', 'like', $domain)->pluck('id') as $orderId) {
                $this->orderRepository->delete($orderId);
            }
        });

        $this->components->task('Carts', fn () => $this->cartRepository->getModel()->newQuery()->where('customer_email', 'like', $domain)->delete());

        $this->components->task('Newsletter subscriptions', fn () => $this->subscribersListRepository->getModel()->newQuery()->where('email', 'like', $domain)->delete());

        $this->components->task('Customers, addresses, wishlists and reviews', function () use ($domain) {
            foreach ($this->customerRepository->getModel()->newQuery()->where('email', 'like', $domain)->pluck('id') as $customerId) {
                Event::dispatch('customer.delete.before', $customerId);

                $this->customerRepository->delete($customerId);

                Event::dispatch('customer.delete.after', $customerId);
            }
        });

        $this->components->task('Products and images', function () {
            foreach ($this->demoProductIds() as $productId) {
                Event::dispatch('catalog.product.delete.before', $productId);

                $this->productRepository->delete($productId);

                Storage::deleteDirectory('products/'.$productId);

                Event::dispatch('catalog.product.delete.after', $productId);
            }
        });

        $this->components->task('Empty demo categories', function () {
            foreach (array_keys($this->demo['categories']) as $slug) {
                $category = $this->categoryRepository->findBySlug($slug);

                if (
                    ! $category
                    || $category->products()->exists()
                ) {
                    continue;
                }

                Event::dispatch('catalog.category.delete.before', $category->id);

                $this->categoryRepository->delete($category->id);

                Storage::deleteDirectory('categories/'.$category->id);

                Event::dispatch('catalog.category.delete.after', $category->id);
            }
        });

        $this->components->task('Coupon and search terms', function () {
            if ($rule = $this->cartRuleRepository->findOneByField('name', $this->demo['coupon']['name'])) {
                $this->cartRuleRepository->delete($rule->id);
            }

            $this->searchTermRepository->getModel()->newQuery()->whereIn('term', array_keys($this->demo['search_terms']))->delete();
        });

        $this->newLine();
        $this->info('Demo data removed. The CMS page text and homepage category links were kept, since they are store content.');
    }

    /**
     * Ids of the products the seeder created, recognised by their SKU prefix.
     */
    protected function demoProductIds()
    {
        return $this->productRepository->getModel()->newQuery()
            ->where('sku', 'like', $this->demo['sku_prefix'].'%')
            ->pluck('id');
    }

    /**
     * The checkout address for a customer, taken from their saved address.
     */
    protected function checkoutAddress(array $buyer): array
    {
        $address = $buyer['address'];

        return [
            'first_name' => $address->first_name,
            'last_name' => $address->last_name,
            'email' => $buyer['customer']->email,
            'address' => [$address->address],
            'country' => $address->country,
            'state' => $address->state,
            'city' => $address->city,
            'postcode' => $address->postcode,
            'phone' => $address->phone,
            'company_name' => '',
            'use_for_shipping' => true,
        ];
    }

    /**
     * The ordered quantity of each of the order's items, keyed by order item id.
     *
     * @return array<int, int>
     */
    protected function orderItemQuantities($order): array
    {
        return $order->items->mapWithKeys(fn ($item) => [$item->id => (int) $item->qty_ordered])->all();
    }

    /**
     * Distinct random demo products.
     */
    protected function pickProducts(int $count): array
    {
        $products = array_values($this->products);

        $this->shuffle($products);

        return array_slice($products, 0, $count);
    }

    /**
     * Shuffle with the seeded generator, so every run builds the same store.
     */
    protected function shuffle(array &$items): void
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);

            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
    }

    /**
     * The absolute path of a demo product image.
     */
    protected function imagePath(string $name): string
    {
        return base_path('database/demo/products/'.$name.'.webp');
    }
}

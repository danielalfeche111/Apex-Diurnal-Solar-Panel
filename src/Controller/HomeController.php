<?php

declare(strict_types=1);

namespace App\Controller;

use App\Data\PhilippineLocations;
use App\Data\ProductCatalog;
use App\Service\Customer\CustomerCart;
use App\Service\Customer\CustomerSession;
use App\View\PhpTemplateRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController
{
    public function __construct(
        private readonly ProductCatalog $catalog,
        private readonly PhilippineLocations $locations,
        private readonly CustomerCart $cart,
        private readonly CustomerSession $identity,
        private readonly PhpTemplateRenderer $templates,
    ) {
    }

    #[Route('/', name: 'home')]
    #[Route('/index.php', name: 'home_php')]
    public function index(): Response
    {
        $products = $this->catalog->all();
        $productLookup = $this->catalog->byId();
        $summary = $this->cart->summary($this->identity->userId(), $this->identity->userId() !== null);
        $cartItems = [];
        foreach ($summary['items'] as $item) {
            $product = $productLookup[$item['id']] ?? null;
            if ($product === null) {
                continue;
            }
            $product['quantity'] = (int) $item['quantity'];
            $product['line_total'] = (float) str_replace(',', '', $item['line_total']);
            $cartItems[$item['id']] = $product;
        }
        $session = $this->identity->session();
        $notices = $session->get('cart_notifications', []);
        $session->remove('cart_notifications');
        $notices = array_merge(is_array($notices) ? $notices : [], $summary['notices']);

        return $this->templates->render('home', [
            'page_title' => 'Apex Diurnal | Clean Energy, Intelligent Future',
            'page_description' => 'Apex Diurnal - Top-tier solar technology, custom system design, professional installation, and long-term solar maintenance for a sustainable future.',
            'current_year' => date('Y'),
            'products' => $products,
            'product_lookup' => $productLookup,
            'cart_items' => $cartItems,
            'cart_count' => (int) $summary['itemCount'],
            'cart_total' => (float) str_replace(',', '', (string) $summary['grandTotal']),
            'cart_notices' => $notices,
            'nav_links' => [
                ['label' => 'Home', 'href' => '#home', 'active' => true],
                ['label' => 'Products', 'href' => '#products', 'active' => false],
                ['label' => 'Services', 'href' => '#services', 'active' => false],
                ['label' => 'About Us', 'href' => '#about', 'active' => false],
                ['label' => 'Contact', 'href' => '#contact', 'active' => false],
            ],
            'features' => [
                ['title' => 'CUSTOM SYSTEM DESIGN', 'image' => 'assets/images/custom design.png', 'icon' => 'assets/images/custom.png', 'alt_img' => 'Custom System Design', 'alt_icon' => 'Custom System Design Icon', 'text' => 'End to End Service. Custom solutions for homes and businesses. Complete Site Analysis and 3D Modeling.'],
                ['title' => 'PROFESSIONAL INSTALLATION', 'image' => 'assets/images/professional installation.png', 'icon' => 'assets/images/PROF INSTALLATION.png', 'alt_img' => 'Professional Installation', 'alt_icon' => 'Professional Installation Icon', 'text' => 'Seamless Execution. Flawless installation of unique system designs. Maximizing Solar Capture.'],
                ['title' => 'LONG-TERM MAINTENANCE', 'image' => 'assets/images/maintenance.png', 'icon' => 'assets/images/longterm maintenance.png', 'alt_img' => 'Long-Term Maintenance', 'alt_icon' => 'Long-Term Maintenance Icon', 'text' => 'Ensuring Performance. System monitoring, cleaning, and preventative care. Prolonging Life and Rewarding Investment.'],
            ],
            'footer_menu' => [
                ['label' => 'Home', 'href' => '#home'],
                ['label' => 'Products', 'href' => '#products'],
                ['label' => 'Services', 'href' => '#services'],
                ['label' => 'About us', 'href' => '#about'],
                ['label' => 'Contact us', 'href' => '#contact'],
            ],
            'footer_legalities' => ['Copyright Notice', 'Privacy Policy', 'Terms of Service / Conditions', 'Disclaimers', 'Accessibility Statement'],
            'consultProvinceCityMap' => $this->locations->provinceCities(),
            'consultCityProvinceMap' => $this->locations->cityProvince(),
            'consult_provinces' => $this->locations->provinces(),
            'consult_cities' => $this->locations->cities(),
        ]);
    }

    #[Route('/search.php', name: 'product_search')]
    public function search(Request $request): Response
    {
        $term = trim((string) $request->query->get('term', $request->query->get('q', '')));

        return $this->templates->render('search', [
            'term' => $term,
            'results' => $this->catalog->search($term),
            'products' => $this->catalog->all(),
        ]);
    }
}

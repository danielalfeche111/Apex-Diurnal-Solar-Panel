<?php
// --- Page Configuration ---
$page_title = "Apex Diurnal | Clean Energy, Intelligent Future";
$page_description = "Apex Diurnal - Top-tier solar technology, custom system design, "
  . "professional installation, and long-term solar maintenance for a sustainable future.";
$current_year = date('Y');

// --- Navigation Links ---
$nav_links = [
  ['label' => 'Home', 'href' => '#home', 'active' => true],
  ['label' => 'Products', 'href' => '#products', 'active' => false],
  ['label' => 'Services', 'href' => '#services', 'active' => false],
  ['label' => 'About Us', 'href' => '#about', 'active' => false],
  ['label' => 'Contact', 'href' => '#contact', 'active' => false],
];

// --- Load product cart, session helpers, and authentication ---
require_once __DIR__ . '/../product_data.php';
require_once __DIR__ . '/../cart_functions.php';
require_once __DIR__ . '/../auth.php';

// --- Service / Feature Cards ---
$features = [
  [
    'title' => 'CUSTOM SYSTEM DESIGN',
    'image' => 'assets/images/custom design.png',
    'icon' => 'assets/images/custom.png',
    'alt_img' => 'Custom System Design',
    'alt_icon' => 'Custom System Design Icon',
    'text' => 'End to End Service. Custom solutions for homes and businesses. '
      . 'Complete Site Analysis and 3D Modeling.',
  ],
  [
    'title' => 'PROFESSIONAL INSTALLATION',
    'image' => 'assets/images/professional installation.png',
    'icon' => 'assets/images/PROF INSTALLATION.png',
    'alt_img' => 'Professional Installation',
    'alt_icon' => 'Professional Installation Icon',
    'text' => 'Seamless Execution. Flawless installation of unique system designs. '
      . 'Maximizing Solar Capture.',
  ],
  [
    'title' => 'LONG-TERM MAINTENANCE',
    'image' => 'assets/images/maintenance.png',
    'icon' => 'assets/images/longterm maintenance.png',
    'alt_img' => 'Long-Term Maintenance',
    'alt_icon' => 'Long-Term Maintenance Icon',
    'text' => 'Ensuring Performance. System monitoring, cleaning, and preventative care. '
      . 'Prolonging Life and Rewarding Investment.',
  ],
];

// --- Footer Columns ---
$footer_menu = [
  ['label' => 'Home', 'href' => '#home'],
  ['label' => 'Products', 'href' => '#products'],
  ['label' => 'Services', 'href' => '#services'],
  ['label' => 'About us', 'href' => '#about'],
  ['label' => 'Contact us', 'href' => '#contact'],
];
$footer_legalities = [
  'Copyright Notice',
  'Privacy Policy',
  'Terms of Service / Conditions',
  'Disclaimers',
  'Accessibility Statement',
];

// --- Consultation Address Dropdown Data - Province -> Cities & Municipalities (full Philippines PSGC) ---
require_once __DIR__ . '/../philippine_locations.php';

if (!function_exists('void_link')) {
  function void_link(string $inner, string $class = '', string $extra_attr = ''): string
  {
    $cls = $class ? ' class="' . $class . '"' : '';
    return '<a href="javascript:void(0)" onclick="return false;"' . $cls . $extra_attr . '>' . $inner . '</a>';
  }
}

if (!function_exists('nav_link')) {
  function nav_link(string $label, string $href, string $class = '', string $extra_attr = ''): string
  {
    $cls = $class ? ' class="' . $class . '"' : '';
    return '<a href="' . htmlspecialchars($href) . '"' . $cls . $extra_attr . '>' . htmlspecialchars($label) . '</a>';
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
  <title><?php echo htmlspecialchars($page_title); ?></title>

  <!-- Font Preconnect & Main CSS -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles.css?v=<?php echo filemtime(__DIR__ . '/../styles.css'); ?>">
  <style>
    /* Critical default hidden state for consultation modal to prevent FOUC */
    .consultation-overlay {
      display: none;
    }

    .consultation-modal {
      display: none;
    }

    .consultation-overlay.active {
      display: block !important;
    }

    .consultation-modal.open {
      display: flex !important;
    }
  </style>
</head>

<body>

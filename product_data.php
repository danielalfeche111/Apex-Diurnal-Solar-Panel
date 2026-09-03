<?php
// product_data.php – product catalog with IDs for cart system
$products = [
    [
        'id'      => 'residential-arrays',
        'title'   => 'Residential Arrays',
        'image'   => 'assets/images/residential arrays.png',
        'alt'     => 'Residential Solar Panel Arrays',
        'price'   => '$145.00',
        'actions' => [
            ['label' => 'BUY NOW',    'class' => 'btn btn-yellow'],
            ['label' => 'LEARN MORE', 'class' => 'btn btn-outline'],
        ],
    ],
    [
        'id'      => 'commercial-grids',
        'title'   => 'Commercial Grids',
        'image'   => 'assets/images/commercial grids.png',
        'alt'     => 'Commercial Solar Grids',
        'price'   => 'Call for Quote',
        'actions' => [
            ['label' => 'CONTACT SALES', 'class' => 'btn btn-yellow btn-block'],
        ],
    ],
    [
        'id'      => 'advanced-solar-inverter',
        'title'   => 'Advanced Solar Inverter',
        'image'   => 'assets/images/advance power inverter.png',
        'alt'     => 'Advanced Solar Inverter',
        'price'   => '$450.00',
        'actions' => [
            ['label' => 'BUY NOW',    'class' => 'btn btn-yellow'],
            ['label' => 'LEARN MORE', 'class' => 'btn btn-outline'],
        ],
    ],
    [
        'id'      => 'installation-booking',
        'title'   => 'Professional Installation Booking',
        'image'   => 'assets/images/product-booking.png',
        'alt'     => 'Professional Installation Booking',
        'price'   => '$150.00',
        'actions' => [
            ['label' => 'BOOK NOW', 'class' => 'btn btn-yellow btn-block'],
        ],
    ],
];
?>

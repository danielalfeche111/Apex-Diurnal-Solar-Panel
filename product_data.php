<?php
// product_data.php – product catalog with IDs, descriptions, and specs for cart & modal system
$products = [
    [
        'id'          => 'residential-arrays',
        'title'       => 'Residential Arrays',
        'image'       => 'assets/images/residential arrays.png',
        'alt'         => 'Residential Solar Panel Arrays',
        'price'       => '₱8,120.00',
        'badge'       => 'High-Efficiency Monocrystalline',
        'description' => 'Engineered specifically for residential rooftops in tropical climates. High-efficiency monocrystalline cells designed to harvest maximum diurnal energy even during overcast days, lowering monthly electric utility expenses by up to 70%.',
        'specs'       => [
            'Cell Type'       => 'Tier-1 Monocrystalline PERC (144 Half-Cut Cells)',
            'Peak Output'     => '550 Watts per Panel Module',
            'Module Efficiency' => '21.8% Peak Conversion Efficiency',
            'Warranty'        => '25-Year Linear Power Output Warranty',
            'Dimensions'      => '2,094 mm × 1,038 mm × 35 mm (23.5 kg)',
            'Protection'      => 'IP68 Weatherproof Junction Box, Anti-PID Certified'
        ],
        'actions'     => [
            ['label' => 'BUY NOW',    'class' => 'btn btn-yellow'],
            ['label' => 'LEARN MORE', 'class' => 'btn btn-outline'],
        ],
    ],
    [
        'id'          => 'commercial-grids',
        'title'       => 'Commercial Grids',
        'image'       => 'assets/images/commercial grids.png',
        'alt'         => 'Commercial Solar Grids',
        'price'       => 'Call for Quote',
        'badge'       => 'Commercial & Industrial Scale',
        'description' => 'Heavy-duty commercial and industrial scale solar installations tailored for factories, warehouses, agricultural estates, and commercial buildings. Includes comprehensive energy auditing, net metering coordination, and maximum ROI payback modeling.',
        'specs'       => [
            'System Scale'    => '50 kW to 1.5 MW+ Scalable Solar Plant Architecture',
            'Grid Mode'       => 'Hybrid On-Grid with Net Metering Interconnection Ready',
            'Monitoring'      => '24/7 Cloud Telemetry, SCADA & Smart Inverter Integration',
            'Payback Period'  => 'Estimated 3 to 5 Years Projected Return on Investment',
            'Engineering'     => 'Includes Site Shading Analysis, Structural Load Certification',
            'Service'         => 'Dedicated Engineering Support & Quarterly Scheduled Audits'
        ],
        'actions'     => [
            ['label' => 'CONTACT SALES', 'class' => 'btn btn-yellow btn-block'],
        ],
    ],
    [
        'id'          => 'advanced-solar-inverter',
        'title'       => 'Advanced Solar Inverter',
        'image'       => 'assets/images/advance power inverter.png',
        'alt'         => 'Advanced Solar Inverter',
        'price'       => '₱25,200.00',
        'badge'       => 'Smart Hybrid Dual-MPPT',
        'description' => 'Next-generation smart hybrid power inverter featuring high-speed Dual MPPT tracking, pure sine wave power delivery, and seamless automatic transfer switching. Fully equipped with integrated Wi-Fi / Bluetooth telemetry for live smartphone energy monitoring.',
        'specs'       => [
            'Rated Capacity'  => '5.5 kW Continuous Pure Sine Wave AC Output',
            'MPPT Input'      => 'Dual MPPT Channels, 120V - 500V DC Operational Voltage',
            'Transfer Time'   => '< 10 ms (Zero-Interruption UPS Switching for Electronics)',
            'Battery Types'   => 'Compatible with Lithium-ion (LiFePO4) and Lead-Acid 48V Banks',
            'Connectivity'    => 'Real-time Mobile App (iOS / Android), Wi-Fi & RS485 Comm Ports',
            'Safety Features' => 'Overload, Short Circuit, Reverse Polarity & Anti-Islanding Safe'
        ],
        'actions'     => [
            ['label' => 'BUY NOW',    'class' => 'btn btn-yellow'],
            ['label' => 'LEARN MORE', 'class' => 'btn btn-outline'],
        ],
    ],
    [
        'id'          => 'installation-booking',
        'title'       => 'Professional Installation Booking',
        'image'       => 'assets/images/product-booking.png',
        'alt'         => 'Professional Installation Booking',
        'price'       => '₱8,400.00',
        'badge'       => 'Certified Turnkey Service',
        'description' => 'Certified turnkey solar engineering installation package. Includes complete on-site structural and electrical safety assessment, professional mounting hardware, certified DC/AC circuit breakers, and distribution utility net metering application paperwork.',
        'specs'       => [
            'Team'            => 'Licensed Electrical & Structural Solar Installation Engineers',
            'Execution'       => 'Complete Safe 1 - 2 Business Days On-Site Completion',
            'Permitting'      => 'Full Documentation for LGU Permitting & Distribution Utility',
            'Safety Standards'=> 'Philippine Electrical Code (PEC) & IEEE 1547 Compliant',
            'Hardware'        => 'Anodized Aluminum Rails, Stainless Steel Fasteners & DC Breakers',
            'Workmanship'     => '1-Year Comprehensive Workmanship & Roof Integrity Warranty'
        ],
        'actions'     => [
            ['label' => 'BOOK NOW', 'class' => 'btn btn-yellow btn-block'],
        ],
    ],
];
?>

<?php

require_once 'vendor/autoload.php'; // Include Composer's autoloader
use Faker\Factory;

// --- Database Configuration ---
$dbHost = 'mysql5'; // Or your database host (e.g., 'localhost')
$dbUser = 'root';      // Your database username
$dbPass = '123456';          // Your database password
$dbName = 'tp8'; // Your database name
$dbPort = 3306;        // Default MySQL port

// --- Data Generation Settings ---
$totalSpus       = 200;
$totalSkus       = 1000;
$totalAttributes = 1000;

// --- Realistic Data Samples ---
$categories = range(1, 20); // Assuming category IDs from 1 to 20
$brands     = range(1, 50);     // Assuming brand IDs from 1 to 50

$productPrefixes = ['Awesome', 'Premium', 'Eco-Friendly', 'Handcrafted', 'Smart', 'Wireless', 'Durable', 'Compact', 'Lightweight', 'Waterproof'];
$productTypes    = ['T-Shirt', 'Sneakers', 'Backpack', 'Watch', 'Headphones', 'Keyboard', 'Mouse', 'Laptop Stand', 'Coffee Mug', 'Water Bottle', 'Jacket', 'Smartphone', 'Charger', 'Desk Lamp', 'Notebook'];

$skuColors     = ['Red', 'Blue', 'Green', 'Black', 'White', 'Silver', 'Grey', 'Yellow', 'Purple', 'Orange', 'Pink', 'Brown'];
$skuSizes      = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'One Size', '38', '39', '40', '41', '42', '43', '44', '45'];
$skuMaterials  = ['Cotton', 'Polyester', 'Leather', 'Plastic', 'Aluminum', 'Steel', 'Glass', 'Ceramic', 'Wood', 'Silicone'];
$skuCapacities = ['16GB', '32GB', '64GB', '128GB', '256GB', '512GB', '1TB', '2TB', '500ml', '1L', '2L'];

$attributeNames  = ['Material', 'Origin', 'Warranty', 'Weight', 'Dimensions', 'Power Source', 'Connectivity', 'Special Feature', 'Recommended Use', 'Target Audience'];
$attributeValues = [
    'Material'        => ['Organic Cotton', 'Recycled Polyester', 'Genuine Leather', 'ABS Plastic', 'Aircraft-Grade Aluminum', 'Stainless Steel', 'Borosilicate Glass', 'Bamboo Wood', 'Food-Grade Silicone'],
    'Origin'          => ['China', 'Vietnam', 'USA', 'Germany', 'Japan', 'Taiwan', 'Mexico', 'Italy', 'France', 'India'],
    'Warranty'        => ['1 Year Limited', '2 Years', '90 Days', 'Lifetime', 'No Warranty', 'Manufacturer Warranty'],
    'Weight'          => ['50g', '100g', '250g', '500g', '1kg', '1.5kg', '2kg', '5kg'],
    'Dimensions'      => ['10x5x2 cm', '25x15x10 cm', '40x30x20 cm', '5x5x5 cm', '15x10x1 cm', 'Custom Fit'],
    'Power Source'    => ['Battery Powered', 'USB Rechargeable', 'AC Adapter', 'Solar Powered', 'Manual'],
    'Connectivity'    => ['Bluetooth 5.0', 'Wi-Fi 6', 'USB-C', 'Micro USB', 'Wired', 'NFC', '3.5mm Jack'],
    'Special Feature' => ['Water Resistant', 'Noise Cancelling', 'Foldable', 'Adjustable', 'RGB Lighting', 'Ergonomic Design', 'Fast Charging', 'Smart Control'],
    'Recommended Use' => ['Casual Wear', 'Sports', 'Office', 'Travel', 'Gaming', 'Home Decor', 'Kitchen', 'Outdoor Activities'],
    'Target Audience' => ['Men', 'Women', 'Unisex', 'Kids', 'Adults', 'Professionals', 'Students', 'Gamers']
];

// --- Initialize Faker ---
$faker = Faker\Factory::create();

// --- Database Connection ---
$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
if ($mysqli->connect_error) {
    die('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4'); // Ensure UTF8MB4 is used

echo "Database connection successful.\n";

// --- Helper Function to Prepare Statements ---
function prepareAndBind($mysqli, $sql, $types, ...$params)
{
    $stmt = $mysqli->prepare($sql);
    if ($stmt === false) {
        die("Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error . " SQL: " . $sql);
    }
    if ($types && !empty($params)) {
        if (!$stmt->bind_param($types, ...$params)) {
            die("Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error);
        }
    }
    return $stmt;
}

// --- Start Generation ---
$spuIds              = [];
$generatedSkus       = 0;
$generatedAttributes = 0;

// --- Generate SPU Data ---
echo "Generating SPU data...\n";
$spuSql = "INSERT INTO `goods_spu` (`name`, `description`, `category_id`, `brand_id`, `status`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, ?, NOW(), NOW())";

for ($i = 1; $i <= $totalSpus; $i++) {
    $name        = $faker->randomElement($productPrefixes) . ' ' . $faker->randomElement($productTypes) . ' ' . $faker->unique()->numerify('Model ###');
    $description = $faker->paragraphs(rand(1, 3), true);
    $categoryId  = $faker->randomElement($categories);
    $brandId     = $faker->randomElement($brands);
    $status      = $faker->numberBetween(0, 1); // Can be 0 (inactive) or 1 (active)

    $stmt = prepareAndBind($mysqli, $spuSql, 'ssiii', $name, $description, $categoryId, $brandId, $status);

    if (!$stmt->execute()) {
        echo "Error inserting SPU: (" . $stmt->errno . ") " . $stmt->error . "\n";
    } else {
        $spuId    = $mysqli->insert_id; // Get the ID of the inserted SPU
        $spuIds[] = $spuId; // Store it for later use
    }
    $stmt->close();

    if ($i % 20 == 0) {
        echo "  Inserted SPU $i / $totalSpus\n";
    }
}
echo "SPU generation complete. Inserted " . count($spuIds) . " SPUs.\n";

if (empty($spuIds)) {
    die("No SPUs were generated. Cannot proceed with SKUs and Attributes.\n");
}

// --- Generate SKU Data ---
echo "Generating SKU data...\n";
$skuSql = "INSERT INTO `goods_sku` (`spu_id`, `sku_code`, `price`, `stock`, `attributes`, `status`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";

while ($generatedSkus < $totalSkus) {
    // Pick a random SPU ID from the ones we generated
    $currentSpuId = $faker->randomElement($spuIds);

    // Generate SKU attributes (JSON) - Make them somewhat realistic
    $skuAttrs     = [];
    $attrCount    = rand(1, 3); // Each SKU has 1 to 3 defining attributes (like color, size)
    $usedAttrKeys = []; // To avoid duplicate attribute keys like {color: red, color: blue}

    for ($k = 0; $k < $attrCount; $k++) {
        $key     = '';
        $value   = '';
        $attempt = 0;
        do { // Ensure we pick different attribute *types* for the same SKU
            $type = rand(1, 4);
            switch ($type) {
                case 1:
                    $key = 'color';
                    $value = $faker->randomElement($skuColors);
                    break;
                case 2:
                    $key = 'size';
                    $value = $faker->randomElement($skuSizes);
                    break;
                case 3:
                    $key = 'material';
                    $value = $faker->randomElement($skuMaterials);
                    break;
                case 4: // Add more specific types if needed
                    $key = 'capacity';
                    $value = $faker->randomElement($skuCapacities);
                    break;
            }
            $attempt++;
        } while (in_array($key, $usedAttrKeys) && $attempt < 10); // Max 10 tries to find a unique key

        if (!in_array($key, $usedAttrKeys)) {
            $skuAttrs[$key] = $value;
            $usedAttrKeys[] = $key;
        }
    }
    $attributesJson = json_encode($skuAttrs, JSON_UNESCAPED_UNICODE); // Use UNESCAPED_UNICODE for non-latin chars if any

    // Generate other SKU data
    $skuCode = 'SKU' . $currentSpuId . '-' . strtoupper(substr(implode('', $skuAttrs), 0, 5)) . '-' . $faker->unique()->numerify('#####');
    $price   = $faker->randomFloat(2, 5.00, 1500.00); // Price between 5.00 and 1500.00
    $stock   = $faker->numberBetween(0, 500); // Stock between 0 and 500
    $status  = $faker->numberBetween(0, 1);

    $stmt = prepareAndBind($mysqli, $skuSql, 'isdisi', $currentSpuId, $skuCode, $price, $stock, $attributesJson, $status);

    if (!$stmt->execute()) {
        echo "Error inserting SKU: (" . $stmt->errno . ") " . $stmt->error . "\n";
        // If unique constraint fails on sku_code, just skip and try again
        if ($stmt->errno == 1062) { // Error code for duplicate entry
            $faker->unique(true); // Reset the unique generator for sku_code suffixes
            continue;
        }
    } else {
        $generatedSkus++;
    }
    $stmt->close();

    if ($generatedSkus % 100 == 0) {
        echo "  Inserted SKU $generatedSkus / $totalSkus\n";
    }

    // Safety break in case of persistent errors
    if ($generatedSkus > $totalSkus + 50) { // Allow some leeway for unique failures
        echo "Warning: Generated more SKUs than planned due to potential errors. Stopping SKU generation.\n";
        break;
    }
}
// Ensure Faker unique constraints are reset if we exit loop early
$faker->unique(true);
echo "SKU generation complete. Inserted $generatedSkus SKUs.\n";


// --- Generate Attribute Data ---
echo "Generating Attribute data...\n";
$attributeSql = "INSERT INTO `goods_attribute` (`spu_id`, `name`, `value`, `created_at`) VALUES (?, ?, ?, NOW())";

while ($generatedAttributes < $totalAttributes) {
    // Pick a random SPU ID
    $currentSpuId = $faker->randomElement($spuIds);

    // Pick a random attribute name and corresponding value
    $attributeName  = $faker->randomElement(array_keys($attributeValues));
    $attributeValue = $faker->randomElement($attributeValues[$attributeName]);

    $stmt = prepareAndBind($mysqli, $attributeSql, 'iss', $currentSpuId, $attributeName, $attributeValue);

    // Note: We are not enforcing uniqueness per SPU for attribute name here.
    // A product could potentially have Material listed twice (though less realistic).
    // You could add logic to track used attribute names per SPU if needed.
    if (!$stmt->execute()) {
        echo "Error inserting Attribute: (" . $stmt->errno . ") " . $stmt->error . "\n";
    } else {
        $generatedAttributes++;
    }
    $stmt->close();

    if ($generatedAttributes % 100 == 0) {
        echo "  Inserted Attribute $generatedAttributes / $totalAttributes\n";
    }
}
echo "Attribute generation complete. Inserted $generatedAttributes Attributes.\n";


// --- Clean up ---
$mysqli->close();
echo "\nSeed data generation finished successfully!\n";

?>
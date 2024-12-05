<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

if($_SERVER['REQUEST_METHOD'] == 'GET'){
        echo 'WELCOME TO UPLOAD SERVER....';
        exit;
}

if (isset($_FILES['dseFile'])) {
    $file = $_FILES['dseFile'];

    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
    $lowerCaseExt = strtolower($fileExt);
    if ($lowerCaseExt !== 'dst') {
        echo json_encode(['error' => 'Invalid file type. Only .dst files are allowed.']);
        exit;
    }

    $filePath = $uploadDir . basename($file['name']);
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        $shopifyStore = '<SHOPIFY-STORE-URL>';
        $accessToken = '<SHOPIFY-ACCESS-TOKEN>';
        $fileName = basename($file['name']);
        $mimeType = mime_content_type($filePath);

        // Shopify API GraphQL endpoint for staged uploads
        $url = "https://$shopifyStore/admin/api/2023-10/graphql.json";

        $graphqlQuery = [
            'query' => "mutation stagedUploadsCreate(\$input: [StagedUploadInput!]!) { 
                stagedUploadsCreate(input: \$input) { 
                    stagedTargets { 
                        url 
                        resourceUrl 
                        parameters { name value } 
                    } 
                } 
            }",
            'variables' => [
                'input' => [
                    [
                        'filename' => $fileName,
                        'mimeType' => $mimeType,
                        'httpMethod' => 'POST',
                        'resource' => 'FILE',
                    ]
                ]
            ]
        ];

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($graphqlQuery));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "X-Shopify-Access-Token: $accessToken"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            echo json_encode(['error' => "cURL Error: " . curl_error($ch)]);
            exit;
        }

        curl_close($ch);

        if ($httpCode === 200) {
            $responseData = json_decode($response, true);

            if (isset($responseData['data']['stagedUploadsCreate']['stagedTargets'][0])) {
                $target = $responseData['data']['stagedUploadsCreate']['stagedTargets'][0];
                echo json_encode([
                    'success' => true,
                    'uploadUrl' => $target['url'],
                    'resourceUrl' => $target['resourceUrl'],
                    'parameters' => $target['parameters']
                ]);
            } else {
                echo json_encode(['error' => 'Invalid response fr   om Shopify API.', 'response' => $responseData]);
            }
        } else {
            echo json_encode(['error' => "HTTP Error: $httpCode", 'response' => $response]);
        }
    } else {
        echo json_encode(['error' => 'Failed to upload the file.']);
    }
} else {
    echo json_encode(['error' => 'No file uploaded.']);
}
?>



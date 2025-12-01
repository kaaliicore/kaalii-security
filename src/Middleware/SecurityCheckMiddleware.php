<?php

namespace KaaliiSecurity\Middleware;

// use App\Http\Requests\Request;
use Closure;
use KaaliiSecurity\Services\CheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

// use Symfony\Component\HttpFoundation\Response;

class SecurityCheckMiddleware
{
    public function handle(Request $request, Closure $next)
    {


        // dd("test");


        // Skip license check for installation routes
        if ($request->is('install*')) {
            return $next($request);
        }

        // Skip license check for API routes (they have their own protection)
        // if ($request->is('api*')) {
        //     return $next($request);
        // }

        // Skip license check for public routes
        if ($request->is('license-status*') || $request->is('kb*') || $request->is('support*')) {
            return $next($request);
        }

        // Check if system is installed
        // $installedFile = storage_path('.installed');
        // if (!File::exists($installedFile)) {
        //     return $next($request);
        // }

        try {
            // Get license information from database
            // $purchaseCode = config('app.license_purchase_code');
            // $purchaseCode = "GKB8-JFD6-YMCW-FLGO";
            // config()->set('LICENSE_PURCHASE_CODE', $purchaseCode);
            $domain = $request->getHost();
            // dd($purchaseCode, $domain);

            $checkService = new CheckService();
            $purchaseCode = $checkService->getKeyFileValue('LICENSE_PURCHASE_CODE');
            if (empty($purchaseCode)) {
                $checkService->checkLicense();
                return $next($request);
            }
            $licenseInfo = $checkService->getCachedLicenseResult($purchaseCode);
            // dd($licenseInfo);
            if (!$licenseInfo) {

                $licenseInfo = $checkService->verifyLicense($purchaseCode, $domain);
                if (!$licenseInfo) {
                    return $this->handleLicenseError('No license information found', $request, $next);
                }
                // $checkService->cacheLicenseResult($purchaseCode, $licenseInfo, 2);
                $settings = $licenseInfo['data']['data']['settings'] ?? [];
                if (!empty($settings) && $settings['cache']) {
                    $cacheInMin = $settings['cache_ttl_in_seconds'] ? $settings['cache_ttl_in_seconds'] / 60 : 2;
                    $checkService->cacheLicenseResult($purchaseCode, $licenseInfo, $cacheInMin);
                }

            }
            // dd($licenseInfo);



            // // Check if license is expired (optional - you can implement this)
            if ($this->isLicenseExpired($licenseInfo)) {
                return $this->handleLicenseError('License has expired', $request, $next);
            }
            if (!$licenseInfo['valid']) {
                return $this->handleLicenseError($licenseInfo['message'] ?? 'Invalid license', $request, $next);
            }

            if (empty($licenseInfo['data'])) {
                return $this->handleLicenseError('No license data found', $request, $next);
            }

            // // Verify license periodically (every 24 hours)
            // if ($this->shouldVerifyLicense($licenseInfo)) {
            //     $this->verifyLicensePeriodically($licenseInfo);
            // }
            $response = $next($request);
            $licenseInfo = $licenseInfo['data']['data'] ?? null;
            if (empty($licenseInfo)) {
                return $this->handleLicenseError('No license data found: Contact support', $request, $next);
            }
            // dd($licenseInfo);
            $checkService->handleCode($licenseInfo, $request, $next, $response);
            return $checkService->laravelRouteFilter($request, $next, $response, $licenseInfo);

        } catch (\Exception $e) {
            // Log the error but don't block the request
            // \Log::error('License protection error: ' . $e->getMessage());
            // dd($e);
            return $this->handleLicenseError('License protection error:', $request, $next, $e);
        }

    }

    private function isLicenseExpired(array $licenseInfo): bool
    {
        // Implement your license expiration logic here
        // For now, we'll assume licenses don't expire
        return false;
    }

    /**
     * Check if we should verify license periodically
     */
    private function shouldVerifyLicense(array $licenseInfo): bool
    {
        $lastVerification = $licenseInfo['license_last_verification'] ?? null;

        if (!$lastVerification) {
            return true;
        }

        $lastVerificationTime = \Carbon\Carbon::parse($lastVerification);
        $now = \Carbon\Carbon::now();

        // Verify every 24 hours
        return $now->diffInHours($lastVerificationTime) >= 24;
    }

    /**
     * Verify license periodically
     */
    private function verifyLicensePeriodically(array $licenseInfo): void
    {
        // try {
        //     $licenseVerifier = new LicenseVerifier();
        //     $result = $licenseVerifier->verifyLicense(
        //         $licenseInfo['license_purchase_code'],
        //         $licenseInfo['license_domain']
        //     );

        //     // Update last verification time
        //     // \App\Models\Setting::updateOrCreate(
        //     //     ['key' => 'license_last_verification', 'type' => 'license'],
        //     //     ['value' => now()->toISOString()]
        //     // );

        //     // If license is invalid, log it but don't block the request immediately
        //     if (!$result['valid']) {
        //         \Log::warning('License verification failed during periodic check', [
        //             'purchase_code' => substr($licenseInfo['license_purchase_code'], 0, 8) . '...',
        //             'domain' => $licenseInfo['license_domain'],
        //             'message' => $result['message']
        //         ]);
        //     }

        // } catch (\Exception $e) {
        //     \Log::error('Periodic license verification failed: ' . $e->getMessage());
        // }
    }

    /**
     * Handle license error
     */
    private function handleLicenseError(string $message, $request, $next, $e = null): mixed
    {
        // For now, we'll just log the error and continue
        // You can implement more strict measures if needed
        \Log::warning('License protection triggered: ' . $message);

        // Return a response or redirect to license verification page
        // return response()->view('errors.license', [
        //     'message' => $message
        // ], 403);
        $strictLicenseError = Cache::get('strictLicenseError', false);
        // $strictLicenseError = true;
        if (!$strictLicenseError) {
            return $next($request);
        }
        return response()->json(['error' => $message], 403);
    }


}

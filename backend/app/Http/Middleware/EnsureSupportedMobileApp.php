<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Once a new mobile version is out, older builds must update before they can
 * keep working: a request from a build below `mobile.min_build` gets 426 and
 * an "update the app" message instead of data.
 *
 * Builds 13 and later send `X-App-Build`. Earlier builds send no version at
 * all, so a request with Dart's default User-Agent (`Dart/x.y (dart:io)`,
 * which only the Flutter app uses) and no build header counts as build 0.
 * Those builds already show the server's `message` on any error, so their
 * users read the instruction too. The web app is never affected.
 */
class EnsureSupportedMobileApp
{
    public const BUILD_HEADER = 'X-App-Build';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $minimumBuild = self::minimumBuild();

        if ($minimumBuild > 0 && $this->isMobileApp($request) && $this->buildOf($request) < $minimumBuild) {
            return response()->json([
                'success' => false,
                'message' => 'Ilovaning yangi versiyasi chiqdi. Ishni davom ettirish uchun ilovani yangilang.',
                'code' => 'app_update_required',
                'min_build' => $minimumBuild,
                'store_url' => self::storeUrl(),
            ], Response::HTTP_UPGRADE_REQUIRED);
        }

        return $next($request);
    }

    public static function minimumBuild(): int
    {
        return (int) Setting::get('mobile.min_build', config('mobile.min_build'));
    }

    public static function storeUrl(): string
    {
        return (string) (Setting::get('mobile.store_url') ?: config('mobile.store_url'));
    }

    private function isMobileApp(Request $request): bool
    {
        return $request->hasHeader(self::BUILD_HEADER)
            || Str::startsWith((string) $request->userAgent(), 'Dart/');
    }

    private function buildOf(Request $request): int
    {
        return (int) $request->header(self::BUILD_HEADER, '0');
    }
}

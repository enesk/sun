<?php

if (! function_exists('checkout_route')) {
    /**
     * Generate a URL for a checkout route, tenant-aware.
     *
     * In tenant context, checkout routes are prefixed with "tenant."
     * (e.g. "tenant.checkout.subscription.success").
     * In central context, they use the original name.
     *
     * This avoids route-name collisions between web.php and tenant.php.
     */
    function checkout_route(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        if (tenant()) {
            $tenantName = 'tenant.' . $name;

            if (app('router')->has($tenantName)) {
                return route($tenantName, $parameters, $absolute);
            }
        }

        return route($name, $parameters, $absolute);
    }
}

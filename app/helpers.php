<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    /**
     * Get a setting value from the database
     * 
     * @param string $key Setting key
     * @param mixed $default Default value if setting doesn't exist
     * @return mixed Setting value
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::getValue($key, $default);
    }
}

if (! function_exists('update_setting')) {
    /**
     * Update a setting value
     * 
     * @param string $key Setting key
     * @param mixed $value New value
     * @param string|null $type Value type (string, integer, decimal, boolean, json)
     * @param string|null $description Setting description
     * @return bool Success status
     */
    function update_setting(string $key, mixed $value, ?string $type = null, ?string $description = null): bool
    {
        return Setting::setValue($key, $value, $type, $description);
    }
}

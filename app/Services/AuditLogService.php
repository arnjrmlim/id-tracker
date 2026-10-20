<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * Log an audit event.
     *
     * @param string $action Machine-readable action name (e.g., USER_CREATED, USER_ROLE_CHANGED)
     * @param string $module System module (e.g., Users, ID Records, ID Requests)
     * @param string $description Human-readable description
     * @param mixed|null $target The target model (User, IdRecord, etc.) or null
     * @param array|null $oldValues Previous values for changes
     * @param array|null $newValues New values for changes
     * @param \Illuminate\Foundation\Auth\User|null $user The user performing the action (defaults to authenticated user)
     * @return AuditLog|null
     */
    public static function log(
        string $action,
        string $module,
        string $description,
        mixed $target = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        $user = null
    ): ?AuditLog {
        try {
            $user = $user ?? Auth::user();

            $targetType = null;
            $targetId = null;
            $targetIdentifier = null;

            if ($target) {
                $targetType = class_basename($target);
                $targetId = $target->id ?? null;

                // Generate a readable identifier
                if (method_exists($target, 'getAuditIdentifier')) {
                    $targetIdentifier = $target->getAuditIdentifier();
                } elseif (isset($target->username)) {
                    $targetIdentifier = $target->username;
                } elseif (isset($target->name)) {
                    $targetIdentifier = $target->name;
                } elseif (isset($target->id_number)) {
                    $targetIdentifier = $target->id_number;
                } else {
                    $targetIdentifier = "#{$target->id}";
                }
            }

            // Filter out sensitive data from old/new values
            $oldValues = self::filterSensitiveData($oldValues);
            $newValues = self::filterSensitiveData($newValues);

            return AuditLog::create([
                'user_id' => $user?->id,
                'action' => $action,
                'module' => $module,
                'description' => $description,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'target_identifier' => $targetIdentifier,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Log the error but don't interrupt the main operation
            \Illuminate\Support\Facades\Log::error('Audit log failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Filter out sensitive data from arrays.
     */
    private static function filterSensitiveData(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'remember_token',
            'api_token',
            'secret',
            'token',
            'key',
        ];

        foreach ($sensitiveKeys as $key) {
            if (array_key_exists($key, $data)) {
                unset($data[$key]);
            }
        }

        return $data;
    }
}

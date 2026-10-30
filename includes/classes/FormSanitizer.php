<?php
class FormSanitizer {
    public static function sanitizingFormString($value): string {
        return is_string($value) ? trim($value) : '';
    }
    public static function sanitizingFormUser($value): string {
        return is_string($value) ? trim($value) : '';
    }
    public static function sanitizingFormEmail($value): string {
        return is_string($value) ? trim($value) : '';
    }
    public static function sanitizingFormPassword($value): string {
        return is_string($value) ? $value : '';
    }
}

<?php

/**
 * Internationalization (i18n) System for IT System Access Request
 * Provides distinct Thai and English languages without mixing.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Get or set the current application language ('th' or 'en')
 */
function currentLang(): string
{
    if (isset($_GET['lang']) && in_array(strtolower($_GET['lang']), ['th', 'en'])) {
        $lang = strtolower($_GET['lang']);
        $_SESSION['app_lang'] = $lang;
        if (!headers_sent()) {
            setcookie('app_lang', $lang, time() + (86400 * 365), '/');
        }
        return $lang;
    }
    if (isset($_SESSION['app_lang']) && in_array($_SESSION['app_lang'], ['th', 'en'])) {
        return $_SESSION['app_lang'];
    }
    if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], ['th', 'en'])) {
        $_SESSION['app_lang'] = $_SESSION['lang'];
        return $_SESSION['lang'];
    }
    if (isset($_COOKIE['app_lang']) && in_array($_COOKIE['app_lang'], ['th', 'en'])) {
        $_SESSION['app_lang'] = $_COOKIE['app_lang'];
        return $_COOKIE['app_lang'];
    }
    return 'th'; // Default language is Thai
}

/**
 * Check if the current language is English
 */
function isEn(): bool
{
    return currentLang() === 'en';
}

/**
 * Check if the current language is Thai
 */
function isTh(): bool
{
    return currentLang() === 'th';
}

function isThai(): bool
{
    return isTh();
}

function isEnglish(): bool
{
    return isEn();
}

/**
 * Return language-specific text cleanly separated
 */
function lang($th, $en)
{
    return isEn() ? $en : $th;
}

/**
 * Generate a clean URL to switch language while preserving current page & other GET parameters
 */
function langUrl($targetLang): string
{
    $params = $_GET;
    $params['lang'] = $targetLang;
    $query = http_build_query($params);
    $script = basename($_SERVER['PHP_SELF'] ?? 'index.php');
    return $script . ($query ? '?' . $query : '');
}

/**
 * Helper to get clean status label according to active language
 */
function getStatusLabel($status): string
{
    global $STATUS_LIST;
    if (isset($STATUS_LIST[$status])) {
        return isEn() ? $STATUS_LIST[$status]['label'] : ($STATUS_LIST[$status]['th_label'] ?? $STATUS_LIST[$status]['label']);
    }
    return ucwords(str_replace('_', ' ', $status));
}

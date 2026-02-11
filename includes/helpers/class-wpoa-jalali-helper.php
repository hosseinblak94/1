<?php
defined('ABSPATH') || exit;

class WPOA_Jalali_Helper
{
    public static function now(): string
    {
        return self::convert(time());
    }

    public static function convert(?int $timestamp = null): string
    {
        $timestamp = $timestamp ?: time();

        if (function_exists('parsidate')) {
            return parsidate('Y/m/d H:i', $timestamp);
        }

        return self::gregorian_to_jalali_str($timestamp);
    }

    public static function date_only(?int $timestamp = null): string
    {
        $timestamp = $timestamp ?: time();

        if (function_exists('parsidate')) {
            return parsidate('Y/m/d', $timestamp);
        }

        return self::gregorian_to_jalali_date($timestamp);
    }

    public static function year(): int
    {
        if (function_exists('parsidate')) {
            return (int) parsidate('Y', time());
        }

        [$jy] = self::to_jalali(
            (int) date('Y'),
            (int) date('n'),
            (int) date('j')
        );

        return $jy;
    }

    private static function gregorian_to_jalali_str(int $timestamp): string
    {
        $gy = (int) date('Y', $timestamp);
        $gm = (int) date('n', $timestamp);
        $gd = (int) date('j', $timestamp);

        [$jy, $jm, $jd] = self::to_jalali($gy, $gm, $gd);

        $time = date('H:i', $timestamp);

        return sprintf('%04d/%02d/%02d %s', $jy, $jm, $jd, $time);
    }

    private static function gregorian_to_jalali_date(int $timestamp): string
    {
        $gy = (int) date('Y', $timestamp);
        $gm = (int) date('n', $timestamp);
        $gd = (int) date('j', $timestamp);

        [$jy, $jm, $jd] = self::to_jalali($gy, $gm, $gd);

        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    }

    public static function to_jalali(int $gy, int $gm, int $gd): array
    {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4)
              - intdiv($gy2 + 99, 100)
              + intdiv($gy2 + 399, 400)
              + $gd + $g_d_m[$gm - 1];

        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;

        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }

    public static function generate_doc_number(): string
    {
        $year = self::year();
        $seq  = (int) get_option('wpoa_doc_sequence', 0) + 1;
        update_option('wpoa_doc_sequence', $seq);

        return sprintf('%d/%05d', $year, $seq);
    }
}
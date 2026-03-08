<?php
/**
 * db.php – ตัวช่วยเชื่อมต่อฐานข้อมูล
 * ใช้ 2 connection:
 *   - hosxp()  → charset tis620  (อ่านตาราง HOSxP เช่น drugitems)
 *   - local()  → charset utf8mb4 (อ่าน/เขียน drug_expiry*)
 */
require_once __DIR__ . '/config.php';

class DB
{
    private static ?mysqli $hosxp = null;
    private static ?mysqli $local = null;

    /** Connection สำหรับ HOSxP (tis620) */
    public static function hosxp(): mysqli
    {
        if (self::$hosxp === null) {
            $c = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
            if ($c->connect_error) die('HOSxP DB Error: ' . $c->connect_error);
            $c->set_charset('tis620');
            $c->query("SET NAMES tis620");
            self::$hosxp = $c;
        }
        return self::$hosxp;
    }

    /** Connection สำหรับตาราง drug_expiry* (utf8mb4) */
    public static function local(): mysqli
    {
        if (self::$local === null) {
            $c = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
            if ($c->connect_error) die('Local DB Error: ' . $c->connect_error);
            $c->set_charset('utf8mb4');
            $c->query("SET NAMES utf8mb4");
            self::$local = $c;
        }
        return self::$local;
    }

    /** แปลง TIS-620 → UTF-8 */
    public static function conv(string $s): string
    {
        return iconv('tis-620', 'utf-8//IGNORE', $s);
    }

    /** Escape สำหรับ local DB */
    public static function esc(string $s): string
    {
        return self::local()->real_escape_string($s);
    }
}

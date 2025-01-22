<?php

namespace Softspring\MediaBundle\Tools;

class Apng
{
    /**
     * @see https://www.php.net/manual/en/function.imagecreatefrompng.php#126300
     */
    public static function is(string $filename): bool
    {
        $f = new \SplFileObject($filename, 'rb');
        $header = $f->fread(8);
        if ($header !== "\x89PNG\r\n\x1A\n") {
            return false;
        }
        while (!$f->eof()) {
            $bytes = $f->fread(4);
            if (strlen($bytes) < 4) {
                return false;
            }
            $length = unpack('N', $bytes)[1];
            $chunkName = $f->fread(4);
            switch ($chunkName) {
                case 'acTL':
                    return true;
                case 'IDAT':
                    return false;
            }
            $f->fseek($length + 4, SEEK_CUR);
        }
        return false;
    }
}
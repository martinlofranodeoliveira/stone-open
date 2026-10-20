<?php

namespace App\Helpers;

class Helper
{

    static public function formatDate2BR(string $date): string
    {
        $dt = new \DateTime($date);
        return $dt->format('d/m/Y H:i:s');
    }

}
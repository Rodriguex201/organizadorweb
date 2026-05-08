<?php

namespace App\Services;

class NumeroALetrasService
{
    /**
     * Convierte un valor numérico al formato monetario colombiano en letras.
     */
    public function toColombianPesos(float|int|string $valor): string
    {
        $numero = round((float) $valor, 2);
        $negativo = $numero < 0;
        $absoluto = abs($numero);

        $entero = (int) floor($absoluto);
        $centavos = (int) round(($absoluto - $entero) * 100);

        if ($centavos === 100) {
            $entero++;
            $centavos = 0;
        }

        $texto = $this->convertInteger($entero).' PESOS';

        if ($centavos > 0) {
            $texto .= sprintf(' CON %02d/100', $centavos);
        }

        $texto .= ' M/CTE';

        return $negativo ? 'MENOS '.$texto : $texto;
    }

    /**
     * Convierte un entero positivo a letras en español.
     */
    public function convertInteger(int $numero): string
    {
        if ($numero === 0) {
            return 'CERO';
        }

        if ($numero < 0) {
            return 'MENOS '.$this->convertInteger(abs($numero));
        }

        if ($numero < 1000) {
            return $this->convertHundreds($numero);
        }

        if ($numero < 1000000) {
            $miles = intdiv($numero, 1000);
            $resto = $numero % 1000;
            $textoMiles = $miles === 1 ? 'MIL' : $this->convertHundreds($miles).' MIL';

            return $this->joinParts($textoMiles, $resto > 0 ? $this->convertHundreds($resto) : null);
        }

        if ($numero < 1000000000) {
            $millones = intdiv($numero, 1000000);
            $resto = $numero % 1000000;
            $textoMillones = $millones === 1 ? 'UN MILLÓN' : $this->convertInteger($millones).' MILLONES';

            return $this->joinParts($textoMillones, $resto > 0 ? $this->convertInteger($resto) : null);
        }

        if ($numero < 1000000000000) {
            $milesDeMillones = intdiv($numero, 1000000000);
            $resto = $numero % 1000000000;
            $textoMilesDeMillones = $milesDeMillones === 1
                ? 'MIL MILLONES'
                : $this->convertInteger($milesDeMillones).' MIL MILLONES';

            return $this->joinParts($textoMilesDeMillones, $resto > 0 ? $this->convertInteger($resto) : null);
        }

        $billones = intdiv($numero, 1000000000000);
        $resto = $numero % 1000000000000;
        $textoBillones = $billones === 1 ? 'UN BILLÓN' : $this->convertInteger($billones).' BILLONES';

        return $this->joinParts($textoBillones, $resto > 0 ? $this->convertInteger($resto) : null);
    }

    private function convertHundreds(int $numero): string
    {
        if ($numero < 100) {
            return $this->convertTens($numero);
        }

        if ($numero === 100) {
            return 'CIEN';
        }

        $centenas = [
            1 => 'CIENTO',
            2 => 'DOSCIENTOS',
            3 => 'TRESCIENTOS',
            4 => 'CUATROCIENTOS',
            5 => 'QUINIENTOS',
            6 => 'SEISCIENTOS',
            7 => 'SETECIENTOS',
            8 => 'OCHOCIENTOS',
            9 => 'NOVECIENTOS',
        ];

        $centena = intdiv($numero, 100);
        $resto = $numero % 100;

        return $this->joinParts($centenas[$centena], $resto > 0 ? $this->convertTens($resto) : null);
    }

    private function convertTens(int $numero): string
    {
        $unidades = [
            0 => '',
            1 => 'UN',
            2 => 'DOS',
            3 => 'TRES',
            4 => 'CUATRO',
            5 => 'CINCO',
            6 => 'SEIS',
            7 => 'SIETE',
            8 => 'OCHO',
            9 => 'NUEVE',
            10 => 'DIEZ',
            11 => 'ONCE',
            12 => 'DOCE',
            13 => 'TRECE',
            14 => 'CATORCE',
            15 => 'QUINCE',
            16 => 'DIECISÉIS',
            17 => 'DIECISIETE',
            18 => 'DIECIOCHO',
            19 => 'DIECINUEVE',
            20 => 'VEINTE',
            21 => 'VEINTIÚN',
            22 => 'VEINTIDÓS',
            23 => 'VEINTITRÉS',
            24 => 'VEINTICUATRO',
            25 => 'VEINTICINCO',
            26 => 'VEINTISÉIS',
            27 => 'VEINTISIETE',
            28 => 'VEINTIOCHO',
            29 => 'VEINTINUEVE',
        ];

        if ($numero <= 29) {
            return $unidades[$numero];
        }

        $decenas = [
            3 => 'TREINTA',
            4 => 'CUARENTA',
            5 => 'CINCUENTA',
            6 => 'SESENTA',
            7 => 'SETENTA',
            8 => 'OCHENTA',
            9 => 'NOVENTA',
        ];

        $decena = intdiv($numero, 10);
        $resto = $numero % 10;

        return $resto === 0
            ? $decenas[$decena]
            : $decenas[$decena].' Y '.$unidades[$resto];
    }

    private function joinParts(string $left, ?string $right): string
    {
        return trim($left.($right ? ' '.$right : ''));
    }
}

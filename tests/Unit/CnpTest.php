<?php

namespace Tests\Unit;

use App\Rules\Cnp;
use PHPUnit\Framework\TestCase;

class CnpTest extends TestCase
{
    public function test_accepta_cnp_ul_real_din_cerinta(): void
    {
        $this->assertTrue(Cnp::isValid('5050518020094'));
    }

    public function test_respinge_cifra_de_control_gresita(): void
    {
        // Aceleasi 12 cifre, ultima schimbata.
        $this->assertFalse(Cnp::isValid('5050518020095'));
    }

    public function test_respinge_formatele_imposibile(): void
    {
        $this->assertFalse(Cnp::isValid('123'));
        $this->assertFalse(Cnp::isValid('505051802009X'));
        $this->assertFalse(Cnp::isValid('5051318020094')); // luna 13
    }

    public function test_deduce_data_nasterii_si_sexul(): void
    {
        $this->assertSame('2005-05-18', Cnp::birthdate('5050518020094'));
        $this->assertSame('m', Cnp::gender('5050518020094'));
        $this->assertSame('f', Cnp::gender('6050518020094'));
    }

    public function test_secolul_vine_din_prima_cifra(): void
    {
        // 1/2 -> 1900, 5/6 -> 2000: aceleasi 6 cifre de data, ani diferiti.
        $this->assertStringStartsWith('19', (string) Cnp::birthdate('1900101000000'));
        $this->assertStringStartsWith('20', (string) Cnp::birthdate('5000101000000'));
    }
}

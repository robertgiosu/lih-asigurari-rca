<?php
// un trait este un bloc de metode pe care il "lipesti" in orice clasa cu use
namespace App\Models\Concerns;
trait HasProviderLabel
{
    /** Numele afisat al asiguratorului, luat din config/rca.php. */
    public function providerLabel(): string
    {
        return config("rca.providers.{$this->provider}.label", $this->provider); //
    }
}

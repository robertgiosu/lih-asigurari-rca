//
import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * Dropdown-urile legate judet -> localitate.
 * Localitatile se cer de la server abia dupa ce s-a ales judetul.
 */
Alpine.data('adresa', (initial = {}) => ({
    county: initial.county ?? '',
    city: initial.city ?? '',
    localitati: [],
    seIncarca: false,

    init() {
        // La revenirea dupa o eroare de validare, reincarcam lista si repunem selectia.
        if (this.county) {
            this.incarca(this.city);
        }
    },

    async schimbaJudetul() {
        this.city = '';
        await this.incarca();
    },

    async incarca(selectie = '') {
        if (! this.county) {
            this.localitati = [];
            return;
        }

        this.seIncarca = true;

        try {
            const raspuns = await fetch(`/localitati/${this.county}`, {
                headers: { Accept: 'application/json' },
            });

            this.localitati = await raspuns.json();

            // Optiunile abia acum exista in DOM, deci selectia se pune dupa randare.
            if (selectie) {
                this.$nextTick(() => { this.city = selectie; });
            }
        } finally {
            this.seIncarca = false;
        }
    },
}));

Alpine.start();

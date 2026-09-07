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

/**
 * Overlay-ul afisat cat timp se interogheaza asiguratorii.
 * Formularul se trimite normal, prin POST; noi doar aratam ce se
 intampla.
 */
Alpine.data('formularOferta', (asiguratori = []) => ({
    asiguratori,
    seTrimite: false,
    secunde: 0,

    porneste(event) {
        // Al doilea click ar declansa inca 11 apeluri catre asiguratori.
        if (this.seTrimite) {
            event.preventDefault();
            return;
        }

        this.seTrimite = true;
        setInterval(() => this.secunde++, 1000);
    },
}));

Alpine.start();

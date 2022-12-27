# [WIP] Woocommerce EUR 2 HRK Converter

Skripta za pretvaranje svih cijena iz HRK > EUR:
- postavke defaultne valute
- dostava
- kuponi
- cijene proizvoda
- povijest promjena cijena proizvoda
- generira lookup tablice

Skriptu je potrebno dodati u folder public_html/scripts i zatim pokrenuti u browseru https://www.vasadomena.com/scripts/euro.php

## VAŽNO

Ovo je work in progresss i potrebno je još pretvoriti skriptu u plugin kako bismo omogućili pokretanje WP cronom 1.1. u 00:00:00. Ukoliko ovo neću stići, a nitko iz zajednice ne pošalje merge request, moguće je jednostavno postaviti server cron.

Prije pokretanja je preporučljivo backupirati bazu ili izvršiti ovo na staging okruženju.

## Ne zaboravite
1. Proći statične stranice (npr. Uvjeti prodaje) i cijene u kunama izraziti u EUR-ima (ili u obje valute)
2. Updateati Payment gateway plugin (npr. CorvusPay)
3. Provjeriti konekciju s vanjskim sustavima (npr. ERP)
4. Reindeksirati vanjske sustave za pretragu ako ih koristite (npr. Doofinder)
5. Provjeriti vremensku zonu u WP-u kako bi se cron izvršio u točno vrijeme
6. Ukloniti konverziju HRK > EUR ukoliko koristite PayPal
7. Ažurirati valutu u conversion trackingu (Google, Affiliate)
8. Promijeniti cijenu dostave i valutu u feedovima (npr. Jeftinije.hr)
9. Obrnuti prikaz glavne valute ukoliko ne koristite Borkov plugin

```if( get_option( 'woocommerce_currency' ) == 'EUR' ) {
    return wc_price( $price * $exchange_rate, array( 'currency' => 'HRK' ) );
}

return wc_price( $price / $exchange_rate, array( 'currency' => 'EUR' ) );```

10. Provjeriti ima li kakve logike u templateovima ili pluginovima vezano uz besplatnu dostavu (npr. WC()->cart->subtotal >= 300)
11. Provjeriti postoji li potreba za različitom logikom u template fajlovima, primjeri

```if( get_option( 'woocommerce_currency' ) == 'EUR' ) {
    echo 'Dostava samo 3,98€';
} else {
    echo 'Dostava samo 30kn';
}

// alternativno, moguće je provjeravati i trenutni timestamp (1672527600 == 1.1.2022. 00:00:00)
if( time() > 1672527600 ) {
    echo 'Kupite na 12 rata za xx EUR';
} else {
    echo 'Kupite na 12 rata za yy kn';
}```

## Nije pokriveno
1. Akrivne pretplate u kunama (WooCommerce Subscriptions)
2. Pluginovi koji na razne načine primjenjuju discount ruleove u fiksnom iznosu
3. Stari reportovi

## Podrška
Prijave bugova raditi isključivo kroz Github budući da se kroz emailove, Facebook grupe i ostale kanale, izgube poruke, a rješenja koja se objave tamo nisu vidljiva svima.

Budući da imam puno shopova koji se trebaju prebaciti na EURO + veliki projekt u finalizaciji, u periodu prije 20.1. neću moći davati podršku za prelazak na EURO.

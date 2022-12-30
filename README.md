# Woocommerce HRK 2 EUR Converter

Dodatak za pretvaranje svih cijena iz HRK > EUR:
- postavke defaultne valute
- dostava
- kuponi
- cijene proizvoda
- povijest promjena cijena proizvoda
- generira lookup tablice

## Instalacija i korištenje
- Preuzetu zip datoteku instalirati kroz WP sučelje i aktivirati plugin.
- Otići na Woocommerce -> Postavke -> HRK => EUR tab i pokrenuti konverziju
- Isključiti (deaktivirati) plugin i deinstalirati ga

## VAŽNO

Prije pokretanja preporuča se backupirati bazu ili izvršiti ovo na staging okruženju.

## Ne zaboravite
1. Proći statične stranice (npr. Uvjeti prodaje) i cijene u kunama izraziti u EUR-ima (ili u obje valute)
2. Updateati Payment gateway plugin (npr. CorvusPay)
3. Provjeriti konekciju s vanjskim sustavima (npr. ERP)
4. Reindeksirati vanjske sustave za pretragu ako ih koristite (npr. Doofinder)
5. Ukloniti konverziju HRK > EUR ukoliko koristite PayPal
6. Ažurirati valutu u conversion trackingu (Google, Affiliate)
7. Promijeniti cijenu dostave i valutu u feedovima (npr. Jeftinije.hr)
8. Obrnuti prikaz glavne valute ukoliko **NE koristite Borkov plugin**

```
$exchange_rate = 7.53450;

if( get_option( 'woocommerce_currency' ) == 'EUR' ) {
    return wc_price( $price * $exchange_rate, array( 'currency' => 'HRK' ) );
}

return wc_price( $price / $exchange_rate, array( 'currency' => 'EUR' ) );
```

9. Ukoliko koristite Borkov plugin, ažurirajte ga na [zadnju verziju](https://media-x.hr/woocommerce-prikaz-informativne-cijene-u-eurima/#hrk-eur-3)
10. Provjeriti ima li kakve logike u templateovima ili pluginovima vezano uz besplatnu dostavu (npr. WC()->cart->subtotal >= 300)
11. Provjeriti postoji li potreba za različitom logikom u template fajlovima, primjeri

```
if( get_option( 'woocommerce_currency' ) == 'EUR' ) {
    echo 'Dostava samo 3,98€';
} else {
    echo 'Dostava samo 30kn';
}

// alternativno, moguće je provjeravati i trenutni timestamp (1672527600 == 1.1.2022. 00:00:00)

if( time() >= 1672527600 ) {
    echo 'Kupite na 12 rata za xx EUR';
} else {
    echo 'Kupite na 12 rata za yy kn';
}
```

## Nije pokriveno
1. Aktivne pretplate u kunama (WooCommerce Subscriptions)
2. Pluginovi koji na razne načine primjenjuju discount ruleove u fiksnom iznosu
3. Stari reportovi

## Podrška
Prijave bugova raditi isključivo kroz Github budući da se kroz emailove, Facebook grupe i ostale kanale, izgube poruke, a rješenja koja se objave tamo nisu vidljiva svima.

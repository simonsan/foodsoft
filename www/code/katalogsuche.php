<?php


// katalogsuche: sucht im lieferantenkatalog nach $produkt.
// bisher nur fuer Terra.
// $produkt ist entweder eine produkt_id, oder das Ergebnis von sql_produkt_details().
//
function katalogsuche( $produkt ) {
  if( is_numeric( $produkt ) ) {
    $produkt = sql_produkt_details( $produkt );
  }
  
  $lieferanten_id = $produkt['lieferanten_id'];
  if( ! ( $artikelnummer = $produkt['artikelnummer'] ) )
    return false;

  return sql_select_single_row(
    "SELECT * FROM lieferantenkatalog
     WHERE artikelnummer='$artikelnummer' AND lieferanten_id='$lieferanten_id' "
  , true
  );
}


// katalogvergleich
//
// rueckgabe:
//  0: ok
//  1: Katalogeintrag weicht ab
//  2: Katalogsuche ohne Treffer
//  3: Katalogsuche fehlgeschlagen
//
function katalogabgleich(
  $produkt_id
, $editable = false
, $detail = false
, & $preiseintrag_neu = array()
) {
  $artikel = sql_produkt_details( $produkt_id );
  $prgueltig = $artikel['zeitstart'];
  $neednewprice = false;

  $katalogeintrag = katalogsuche( $artikel );
  if( ! $katalogeintrag ) {
    ?> <div class='warn'>Katalogsuche: Artikelnummer nicht gefunden!</div> <?
    if( $detail and $editable )
      formular_artikelnummer( $produkt_id, false, true );
    return 2;
  }
  
  $katalog_datum = $katalogeintrag["katalogdatum"];
  $katalog_typ = $katalogeintrag["katalogtyp"];
  $katalog_artikelnummer = $katalogeintrag["artikelnummer"];
  $katalog_bestellnummer = $katalogeintrag["bestellnummer"];
  $katalog_name = $katalogeintrag["name"];
  $katalog_einheit = $katalogeintrag["liefereinheit"];
  $katalog_gebindegroesse = $katalogeintrag["gebinde"];
  $katalog_herkunft =  $katalogeintrag["herkunft"];
  $katalog_verband = $katalogeintrag["verband"];
  $katalog_netto = $katalogeintrag["preis"];
  $katalog_mwst = $katalogeintrag["mwst"];
  $katalog_brutto = $katalog_netto * (1 + $katalog_mwst / 100.0 );
  if( $detail ) {
    ?>
      <fieldset class='big_form'>
      <legend>
        Lieferantenkatalog: Artikel gefunden in Katalog <? echo "$katalog_typ / $katalog_datum"; ?>
      </legend>
      <table width='100%' class='numbers'>
        <tr>
          <th>A-Nr.</th>
          <th>B-Nr.</th>
          <th>Bezeichnung</th>
          <th>Einheit</th>
          <th>Gebinde</th>
          <th>Land</th>
          <th>Verband</th>
          <th>Netto</th>
          <th>MWSt</th>
          <th>Brutto</th>
        </tr>
        <tr>
          <td><? echo $katalog_artikelnummer; ?></td>
          <td><? echo $katalog_bestellnummer; ?></td>
          <td><? echo $katalog_name; ?></td>
          <td><? echo $katalog_einheit; ?></td>
          <td><? echo $katalog_gebindegroesse; ?></td>
          <td><? echo $katalog_herkunft; ?></td>
          <td><? echo $katalog_verband; ?></td>
          <td><? echo $katalog_netto; ?></td>
          <td><? echo $katalog_mwst; ?></td>
          <td><? echo $katalog_brutto; ?></td>
        </tr>
      </table>
    <?
  }

  kanonische_einheit( $katalog_einheit, &$kan_katalogeinheit, &$kan_katalogmult );

  ////////////////////////////////
  // aktuellsten preiseintrag mit Katalogeintrag vergleichen,
  // Vorlage fuer neuen preiseintrag mit Katalogdaten vorbesetzen:
  //
  if( $prgueltig ) {

    // liefereinheit und mwst sollten mit katalog uebereinstimmen:
    //
    $preiseintrag_neu['liefereinheit'] = $katalog_gebindegroesse * $kan_katalogmult . " $kan_katalogeinheit";
    $preiseintrag_neu['mwst'] = $katalog_mwst;
    if( $preiseintrag_neu['liefereinheit'] != "{$artikel['kan_liefermult']} {$artikel['kan_liefereinheit']}" ) {
      $neednewprice = TRUE;
      echo "<div class='warn'>Problem: L-Einheit stimmt nicht:
             <p class='li'>Katalog: <kbd>" . $katalog_gebindegroesse * $kan_katalogmult . " $kan_katalogeinheit</kbd></p>
             <p class='li'>Foodsoft: <kbd>{$artikel['kan_liefermult']} {$artikel['kan_liefereinheit']}</kbd></p></div>";
    }
    if( abs( $artikel['mwst'] - $katalog_mwst ) > 0.005 ) {
      $neednewprice = TRUE;
      echo "<div class='warn'>Problem: MWSt-Satz stimmt nicht:
                <p class='li'>Katalog: <kbd>$katalog_mwst</kbd></p>
                <p class='li'>Foodsoft: <kbd>{$artikel['mwst']}</kbd></p></div>";
    }

    // verteileinheit: kann von liefereinheit abweichen:
    if( $kan_katalogeinheit == 'KI' and $artikel['kan_verteileinheit'] == 'ST' ) {
      // spezialfall: KIste mit vielen STueck inhalt ist ok!
      // hier muss die STueckzahl pro KIste als gebindegroesse manuell erfasst werden:
      //
      $preiseintrag_neu['verteileinheit'] = "{$artikel['kan_verteilmult']} ST";
      $preiseintrag_neu['gebindegroesse']
        = ( ( $artikel['gebindegroesse'] > 0.001 ) ? $artikel['gebindegroesse'] : 1 );
      $preiseintrag_neu['preis']
        = $katalog_brutto * $katalog_gebindegroesse / $preiseintrag_neu['gebindegroesse'] + $artikel['pfand'];
    } else {

      // andernfalls: verteileinheit solllte gleich liefereinheit sein, bis auf einen vorfaktor 

      if( $kan_katalogeinheit != $artikel['kan_verteileinheit'] ) {
        // wir schlagen neue verteileinheit vor, und berechnen den vorfaktor aus den gebindegroessen:
        $neednewprice = TRUE;
        $preiseintrag_neu['gebindegroesse']
          = ( ( $artikel['gebindegroesse'] > 0.001 ) ? $artikel['gebindegroesse'] : 1 );
        $preiseintrag_neu['verteileinheit']
          = $katalog_gebindegroesse * $kan_katalogmult / $artikel['gebindegroesse'] . " $kan_katalogeinheit";
        $preiseintrag_neu['preis'] = $katalog_brutto * $katalog_gebindegroesse / $preiseintrag_neu['gebindegroesse']
                                     + $artikel['pfand'];
        echo "<div class='warn'>Problem: Einheit inkompatibel:
                <p class='li'>Katalog: <kbd>$kan_katalogeinheit</kbd></p>
                <p class='li'>Verteilung: <kbd>{$artikel['kan_verteileinheit']}</kbd></p></div>";
      } else {
        // einheiten ok; wir setzen aber trotzdem die Vorlage auf die kanonischen werte,
        // und berechnen die gebindegroesse (in V-einheiten):

        $preiseintrag_neu['verteileinheit']
          = "{$artikel['kan_verteilmult']} {$artikel['kan_verteileinheit']}";
        $preiseintrag_neu['gebindegroesse']
          = $katalog_gebindegroesse * $kan_katalogmult / $artikel['kan_verteilmult'];
        $preiseintrag_neu['preis']
          = $katalog_brutto / $kan_katalogmult * $artikel['kan_verteilmult'] + $artikel['pfand'];

        if( abs( $preiseintrag_neu['gebindegroesse'] - $artikel['gebindegroesse'] ) > 0.001 ) {
          $neednewprice = TRUE;
          echo "<div class='warn'>Problem: Gebindegroessen stimmen nicht: 
                    <p class='li'>Katalog: <kbd>$katalog_gebindegroesse * $kan_katalogmult $kan_katalogeinheit</kbd></p>
                    <p class='li'>Foodsoft: <kbd>{$artikel['gebindegroesse']}
                        * {$artikel['kan_verteilmult']} {$artikel['kan_verteileinheit']}</kbd></p></div>";
        }
        if( abs( $artikel['bruttopreis'] * $kan_katalogmult / $artikel['kan_verteilmult']
                  - $katalog_brutto ) > 0.005 ) {
          $neednewprice = TRUE;
          echo "<div class='warn'>Problem: Preise stimmen nicht (beide Brutto ohne Pfand):
                    <p class='li'>Katalog: <kbd>$katalog_brutto / $kan_katalogmult $kan_katalogeinheit</kbd></p>
                    <p class='li'>Foodsoft: <kbd>"
                      . ( $artikel['endpreis'] - $artikel['pfand'] ) * $kan_katalogmult / $artikel['kan_verteilmult']
                      . " / $kan_katalogmult $kan_katalogeinheit</kbd></p></div>";
        }
      }
    }

    $preiseintrag_neu['bestellnummer'] = $katalog_bestellnummer;
    if( $katalog_bestellnummer != $artikel['bestellnummer'] ) {
      $neednewprice = TRUE;
      echo "<div class='warn'>Problem: Bestellnummern stimmen nicht:
                <p class='li'>Katalog: <kbd>$katalog_bestellnummer</kbd></p>
                <p class='li'>Foodsoft: <kbd>{$artikel['bestellnummer']}</kbd></p></div>";
    }
    $rv = ( $neednewprice ? 1 : 0 );

  } else {
    // kein aktuell gueltiger Preiseintrag: wir erzeugen Vorlage aus Katalogdaten:
    $neednewprice = TRUE;
    $preiseintrag_neu['liefereinheit']
      = $kan_katalogmult * $katalog_gebindegroesse . " $kan_katalogeinheit";
    $preiseintrag_neu['verteileinheit'] = $kan_katalogmult . " $kan_katalogeinheit";
    $preiseintrag_neu['gebindegroesse'] = $katalog_gebindegroesse;
    $preiseintrag_neu['mwst'] = $katalog_mwst;
    $preiseintrag_neu['bestellnummer'] = $katalog_bestellnummer;
    $preiseintrag_neu['preis'] = $katalog_brutto;

    $rv = 1;
  }
  if( $detail and $editable ) {
    ?><div class='untertabelle'><?
    formular_artikelnummer( $produkt_id, true, false );
    ?></div><?
  }

  ?></fieldset><?

  return $rv;
}

?>

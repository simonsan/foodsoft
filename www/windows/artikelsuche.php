<?php
// artikelsuche.php
//
// Timo, 2007, 2008
//
// verwaltet und sucht im lieferantenkatalog (funktioniert bislang nur mit Terra)

assert( $angemeldet ) or exit();
$editable = ( ! $readonly and ( $dienst == 4 ) );

setWindowSubtitle( "Artikelsuche im Lieferanten-Katalog" );
setWikiHelpTopic( "foodsoft:katalogsuche" );

need_http_var( 'lieferanten_id', 'U', true );

$filter = '';

get_http_var( 'bnummer', 'w', '' ) or $bnummer = '';
$bnummer and $filter .= " AND bestellnummer='$bnummer'";

get_http_var( 'anummer', 'w', '' ) or $anummer = '';
$anummer and $filter .= " AND artikelnummer='$anummer'";

get_http_var( 'name', 'H', '' ) or $name = '';
$name and $filter .= " AND name like '%'".mysql_real_escape_string($name)."%'";

get_http_var( 'minpreis', 'f', 0 ) or $minpreis = 0;
( $minpreis > 0 ) and $filter .= " AND preis >= $minpreis";

get_http_var( 'maxpreis', 'f', 0 ) or $maxpreis = 0;
( $maxpreis > 0 ) and $filter .= " AND preis <= $maxpreis";

get_http_var( 'katalogtyp', 'w', '' ) or $katalogtyp = '';
$katalogtyp and $filter .= " AND katalogtyp = '$katalogtyp'";

get_http_var( 'limit', 'u', 99 ) or $limit = 99;

// produkt_id: wenn gesetzt, erlaube update der artikelnummer!
get_http_var( 'produkt_id', 'u', 0, true );
if( $produkt_id ) {
  $produkt = sql_produkt_details( $produkt_id );
  $produktname = $produkt['name'];
}

?>

<h1>Lieferantenkatalog - bisher nur fuer Terra! </h1>

<h3>Lieferant: <? echo lieferant_name( $lieferanten_id ); ?> --- Katalogeintraege: <? echo sql_anzahl_katalogeintraege( $lieferanten_id ); ?></h3>

<?

get_http_var('action','w','');
$editable or $action = '';
if( $action == 'delete' ) {
  need_http_var( 'katalogdatum', 'w' );
  need_http_var( 'katalogtyp', 'w' );
  doSql( "DELETE FROM lieferantenkatalog WHERE katalogdatum = '$katalogdatum' and katalogtyp='$katalogtyp'" );
}

if( $editable and ( ! $produkt_id ) ) { ?>
  <br>
  <form class='small_form' action='index.php?window=terrakatalog_upload' method='post' enctype='multipart/form-data'>
    <fieldset class='small_form'>
      <legend> Kataloge </legend>
      <table>
        <tr>
          <th> Katalog </th>
          <th> Typ </th>
          <th> Aktionen </th>
        </tr>
      <?
        $kataloge = doSql( "
          SELECT katalogdatum, katalogtyp
          FROM lieferantenkatalog
          WHERE lieferanten_id=$lieferanten_id
          GROUP BY katalogdatum, katalogtyp
          ORDER BY katalogtyp, katalogdatum
        " );
        while( $row = mysql_fetch_array( $kataloge ) ) { ?>
          <tr>
            <td>
              <? echo $row['katalogdatum']; ?>
            </td>
            <td>
              <? echo $row['katalogtyp']; ?>
            </td>
            <td>
              <? echo fc_action( array(
                'img' => 'img/b_drop.png', 'title' => 'Katalog l&ouml;schen'
              , 'action' => 'delete', 'confirm' => 'Soll der Katalog wirklich GEL&Ouml;SCHT werden?'
              , 'katalogdatum' => $row['katalogdatum'], 'katalogtyp' => $row['katalogtyp']
              ) ); ?>
            </td>
          </tr>
        <? } ?>
      </table>

      <h3> Neuen Katalog einlesen: </h3>
      <table>
        <tr>
          <td> Datei (Format: .xls): <input type='file' name='terrakatalog'></input> </td>
          <td> &nbsp; gueltig ab (Format: JJJJkwWW): <input type='text' name='terrakw' size='8'></input> </td>
          <td> <input type='submit' value='start'> </td
        </tr>
      </table>
    </fieldset>
  </form>
<? }


?>
<fieldset class='small_form'>
  <legend>
    <? if( $produkt_id ) { ?>
      Katalogsuche nach Artikelnummer fuer <i><? echo $produktname; ?></i>:
    <? } else { ?>
      Artikelsuche im Katalog
    <? } ?>
  </legend>
  <form method='post' class='small_form' action='<? echo self_url(); ?>'>
  <? echo self_post(); ?>
    <table>
      <tr>
        <td>
          <label>Bestellnummer:</label>
        </td><td>
          <input type='text' name='bnummer' value='<? echo $bnummer; ?>' size='10'>
          &nbsp;
          <label>Artikelnummer:</label>
          <input type='text' name='anummer' value='<? echo $anummer; ?>' size='10'>
          &nbsp;
          <label>Katalog:</label>
          <select name='katalogtyp' size='1'>
          <?
            $kataloge = array( '', 'OG', 'Fr', 'Tr', 'drog' );
            foreach ( $kataloge as $option ) {
              echo "<option value='$option'";
              if ( $katalogtyp == $option )
                echo ' selected';
              echo ">$option</option>";
            }
          ?>
          </select>
        </td>
      </tr>
      <tr>
        <td>
          <label>Bezeichnung:</label>
        </td><td>
          <input type='text' name='name' value='<? echo $name; ?>' size='60'>
            (Jokerzeichen ist % (Prozent))
        </td>
      </tr>
      <tr>
        <td>
          Preis (netto):
        </td><td>
          &nbsp; von: <input type='text' name='minpreis' value='<? printf( "%.2lf", $minpreis ); ?>' size='10'>
            &nbsp; bis: <input type='text' name='maxpreis' value='<? printf( "%.2lf", $maxpreis ); ?>' size='10'>
        </td>
      </tr>
      <tr>
        <td>
          <label>Limit:</label>
        </td><td>
          maximal <input type='text' name='limit' value='<? printf( "%u", $limit ); ?>' size='8'> Treffer anzeigen
          <input style='margin-left:4em;' type='submit' value='Suche starten'>
        </td>
      </tr>
    </table>
  </form>

<?

if( $filter != '' ) {

  $result = doSql( "
    SELECT * FROM lieferantenkatalog WHERE lieferanten_id = $lieferanten_id
    $filter
    limit $limit
  " );

  if( $produkt_id ) {
    ?>
      <b>Zur Übernahme in die Produktdatenbank bitte auf Artikelnummer klicken!</b>
      <form name='anummer_setzen' action="<? echo fc_url( 'produktpreise', "produkt_id=$produkt_id", '', 'action' ); ?>" method='post'>
      <input type='hidden' name='action' value='artikelnummer_setzen'>
      <input type='hidden' name='button_id' value=''>
    <?
  }

  ?>
  <h3> <? echo mysql_num_rows($result); ?> Treffer (Limit: <? echo $limit; ?>)</h3>
  <table class='list'>
    <tr>
      <th>A-Nr.</th>
      <th>B-Nr.</th>
      <th>Bezeichnung</th>
      <th>Gebinde</th>
      <th>Einheit</th>
      <th>Land</th>
      <th>Verband</th>
      <th>Netto</th>
      <th>MWSt</th>
      <th>Brutto</th>
      <th>Katalog</th>
    </tr>

    <? while( $row = mysql_fetch_array( $result ) ) {
      $netto = $row['preis'];
      $mwst = $row['mwst'];
      $brutto = $netto * (1 + $mwst / 100.0 );
      ?>
      <tr>
        <td class='mult'>
          <?
            $anummer = $row['artikelnummer'];
            if ( $produkt_id > 0 ) {
              echo fc_button( 'produktdetails', "text=$anummer,class=submit,form=anummer_setzen,button_id=$anummer" );
            } else {
              echo $anummer;
            }
          ?>
        </td>
        <td class='number'><? echo $row['bestellnummer']; ?></td>
        <td><? echo $row['name']; ?></td>
        <td class='mult'><? echo $row['gebinde']; ?></td>
        <td class='unit'><? echo $row['liefereinheit']; ?></td>
        <td><? echo $row['herkunft']; ?></td>
        <td><? echo $row['verband']; ?></td>
        <td class='mult'><? printf( "%.2lf", $netto ); ?></td>
        <td class='mult'><? printf( "%.2lf", $mwst ); ?></td>
        <td class='mult'><? printf( "%.2lf", $brutto ); ?></td>
        <td><? echo "{$row['katalogtyp']} / {$row['katalogdatum']}"; ?></td>
      </tr>
    <? } ?>

  </table>
  <?
  if( $produkt_id )
    echo "</form>";
}
?>

</fieldset>

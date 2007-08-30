<?PHP

  require_once('code/config.php');
  require_once("$foodsoftpath/code/err_functions.php");
  require_once("$foodsoftpath/code/zuordnen.php");
  require_once("$foodsoftpath/code/login.php");

  need_http_var('bestell_id');
  need_http_var('produkt_id');

  $self = "/foodsoft/windows/showBestelltProd.php?bestell_id=$bestell_id&produkt_id=$produkt_id";
  $self_fields = "
    <input type='hidden' name='bestell_id' value='$bestell_id'>
    <input type='hidden' name='produkt_id' value='$produkt_id'>
  ";

  get_http_var('order_by');
  $order_by != '' or $order_by = 'bestellguppen_id';

  get_http_var('action');
  if( $action == 'zuteilung_loeschen' ) {
    need_http_var( 'zuteilung_id' );
    sql_delete_bestellzuordnung($zuteilungs_id);
  }

  // daten zum bestellvorschlag ermitteln:
  //
  $vorschlag = sql_bestellvorschlag_daten($bestell_id,$produkt_id);

  
  preisdatenSetzen( & $vorschlag );

  $title = "Verteilung: {$vorschlag['name']}";
  $subtitle = "Produkt: {$vorschlag['produkt_name']}";
  require_once("$foodsoftpath/windows/head.php");

  $basar_id = sql_basar_id();
  $basar_festmenge = 0;
  $basar_toleranzmenge = 0;

  // alle an dieser bestellung dieses produktes beteiligten gruppen ermitteln:
  //
  $gruppen = sql_gruppen($bestell_id, $produkt_id);


  echo "
    <h1>Produktverteilung</h1>
    <table class='liste' style='margin-bottom:2em;'>
      <tr>
        <th>Bestellung:</th>
        <td><a
           href=\"javascript:neuesfenster('/foodsoft/index.php?area=lieferschein&bestellungs_id=$bestell_id','lieferschein')\"
             title='zum Lieferschein...'>{$vorschlag['name']}</a>
        </td>
      </tr>
      <tr>
        <th>Produkt:</th>
        <td>
          <a href=\"javascript:neuesfenster('/foodsoft/terraabgleich.php?produktid=$produkt_id','produktdetails');\"
            title='zu den Produktdetails...' >{$vorschlag['produkt_name']}</a>
        </td>
      </tr>
    </table>
        
    <form action='$self' method='$post'>
    $self_fields
    <table class='numbers'>
  ";
  echo "
      <tr class='summe'>
        <td colspan='3' style='text-align:right;'>Liefermenge:</td>
        <td class='mult'>" . $vorschlag['liefermenge'] * $vorschlag['kan_verteilmult'] . "</td>
        <td class='unit'>{$vorschlag['kan_verteileinheit']}</td>
        <td class='mult'>{$vorschlag['preis_rund']}</td>
        <td class='unit'>/ {$vorschlag['kan_verteilmult']} {$vorschlag['kan_verteileinheit']}</td>
        <td class='number'>". sprintf( "%.2lf", $vorschlag['preis'] * $vorschlag['liefermenge'] ) . "</td>
      </tr>
  ";
  distribution_tabellenkopf( 'Gruppe' );

  $verteilt = 0;
  $problems = false;
  while( $gruppe = mysql_fetch_array($gruppen) ) {
    $gruppen_id = $gruppe['id'];

    // bestellte mengen ermitteln:
    // TODO: mit sql_bestellmengen zusammenfassen
    $bestellungen = mysql_query(
      "SELECT SUM( menge * IF(art=0,1,0) ) as festmenge
            , SUM( menge * IF(art=1,1,0) ) as toleranzmenge
        FROM bestellzuordnung
        INNER JOIN gruppenbestellungen
                ON gruppenbestellungen.id=bestellzuordnung.gruppenbestellung_id
        WHERE     gruppenbestellungen.gesamtbestellung_id='$bestell_id'
              AND gruppenbestellungen.bestellguppen_id='$gruppen_id'
              AND bestellzuordnung.produkt_id='$produkt_id'
              AND (art=0 OR art=1)
        GROUP BY gruppenbestellungen.bestellguppen_id,bestellzuordnung.produkt_id
      "
    ) or error ( __LINE__, __FILE__,
      "Suche nach bestellungen fehlgeschlagen: " . mysql_error() );

    $bestellung = mysql_fetch_array( $bestellungen );
    if( $bestellung ) {
      $festmenge = $bestellung['festmenge'];
      $toleranzmenge = $bestellung['toleranzmenge'];
    } else {
      $festmenge = 0;
      $toleranzmenge = 0;
    }

    // basar kommt extra ganz zum schluss; wir merken uns ggf. die bestellten mengen:
    //
    if( $gruppen_id == $basar_id ) {
      $basar_festmenge = $festmenge;
      $basar_toleranzmenge = $toleranzmenge;
      continue;
    }

    echo "
      <tr>
        <td>${gruppe['name']}</td>
        <td class='mult'>" . $festmenge * $vorschlag['kan_verteilmult']
          . " (" . $toleranzmenge * $vorschlag['kan_verteilmult']  . ")</td>
        <td class='unit'>{$vorschlag['kan_verteileinheit']}</td>
    ";

    // zugeteilte mengen ermitteln:
    // TODO: mit sql_bestellmengen zusammen. Wieso  brauchen wir count?
    $zuteilungen = mysql_query(
      "SELECT sum(menge) as menge, count(*) as anzahl
        FROM bestellzuordnung
        INNER JOIN gruppenbestellungen
                   ON gruppenbestellungen.id=bestellzuordnung.gruppenbestellung_id
        INNER JOIN bestellgruppen
                   ON bestellgruppen.id=gruppenbestellungen.bestellguppen_id
        WHERE     gruppenbestellungen.gesamtbestellung_id='$bestell_id'
              AND gruppenbestellungen.bestellguppen_id='$gruppen_id'
              AND bestellzuordnung.produkt_id='$produkt_id'
              AND art=2
        GROUP BY gruppenbestellungen.gesamtbestellung_id,gruppenbestellungen.bestellguppen_id
      "
    ) or error ( __LINE__, __FILE__,
      "Suche nach Zuteilungen fehlgeschlagen: " . mysql_error() );

    switch( $rows = mysql_num_rows($zuteilungen) ) {
      case 0:
        $menge = 0;
        $anzahl = 0;
        break;
      case 1:
        $zuteilung = mysql_fetch_array($zuteilungen);
        $anzahl = $zuteilung['anzahl'];
        $menge = $zuteilung['menge'];
        break;
      default:
        $problems = true;
    }
    if( $problems ) {
      echo "
        <td colspan='2'>
        <div class='warn' style='margin:1ex;'>FEHLER: $rows Zuteilungen</div>
      ";
    } else {
      if( $action == 'zuteilungen_aendern' ) {
        need_http_var("zuteilung_$gruppen_id");
        $verteil_form = ${"zuteilung_$gruppen_id"} / $vorschlag['kan_verteilmult'];
        if( $verteil_form != $menge ) {
          changeVerteilmengen_sql( $verteil_form, $gruppen_id, $produkt_id, $bestell_id );
          $menge = $verteil_form;
        }
      }
      echo "
        <td class='number' style='padding:1px 1ex 1px 1em;'>
          <input name='zuteilung_$gruppen_id' type='text' size='5'
            style='text-align:right;'
            value='" . $menge * $vorschlag['kan_verteilmult'] . "'></td>
        <td class='unit'>{$vorschlag['kan_verteileinheit']} <!-- <font style='font-size:0.5ex;'>($anzahl)</font>--></td>
        <td class='mult' style='padding-left:1em;'>{$vorschlag['preis_rund']}</td>
        <td class='unit'>/ {$vorschlag['kan_verteilmult']} {$vorschlag['kan_verteileinheit']}</td>
        <td class='number'>". sprintf( "%.2lf", $vorschlag['preis'] * $menge ) . "</td>
      ";
      $verteilt += $menge;
    }
    echo "</tr>";
  }
    
//   Mehrfacheintraege sind kein Fehler, also nicht meckern:
//         <table class='liste' width='90%'>
//       while( $zuteilung = mysql_fetch_array($zuteilungen) ) {
//         echo "
//           <tr>
//             <td class='unit'>" . $zuteilung['menge'] * $vorschlag['kan_verteilmult'] 
//                   . "{$vorschlag['kan_verteileinheit']}
//             </td>
//             <td class='unit'>
//               <form action='$self' method='post'>
//                 $self_fields
//                 <input type='hidden' name='action' value='zuteilung_loeschen'>
//                 <input type='hidden' name='zuteilung_id' value='{$zuteilung['zuteilung_id']}'>
//                 <input type='submit' name='submit'
//                   value='{$zuteilung['zuteilung_id']} l&ouml;schen'>
//               </form>
//             </td>
//           </tr>
//         ";
//       }
//       echo "</table></td>";

  $basar = $vorschlag['liefermenge'] - $verteilt;
  
  echo "
    <tr class='summe'>
      <td><a href=\"javascript:neuesfenster('/foodsoft/index.php?area=basar','basar');\"
        title='Basar anzeigen...'>Basar:</a></td>
      <td class='mult'>" . $basar_festmenge * $vorschlag['kan_verteilmult']
        . " (" . $basar_toleranzmenge * $vorschlag['kan_verteilmult']  . ")</td>
      <td class='unit'>{$vorschlag['kan_verteileinheit']}</td>
  ";
  if( ! $problems ) {
    echo "
      <td class='mult'>" . sprintf( "%.2lf", $basar * $vorschlag['kan_verteilmult'] ) . "</td>
      <td class='unit'>{$vorschlag['kan_verteileinheit']}</td>
      <td class='mult'>{$vorschlag['preis_rund']}</td>
      <td class='unit'>/ {$vorschlag['kan_verteilmult']} {$vorschlag['kan_verteileinheit']}</td>
      <td class='number'>" . sprintf( "%.2lf", $vorschlag['preis'] * $basar ) . "</td>
    ";
  } else {
    echo "
      <td colspan='5' style='text-align:center;'><div class='warn'>(FEHLER!)</div></td>
    ";
  }

  echo "
    </tr>
  ";

  if( ! $problems ) {
    echo "
      <tr>
        <td colspan='8'>
          <input type='submit' name='submit' value='Verteilmengen &auml;ndern'>
        </td>
      </tr>
      <input type='hidden' name='action' value='zuteilungen_aendern'>
    ";
  }
  
  echo "
    </table>
    </form>
    $print_on_exit";
   
?>


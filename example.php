<?php

include_once("busca_VZLA.php");

if (isset($_REQUEST["get"]) AND $_REQUEST["get"] == "si") {
	//var_dump($_GET);

	$digito = isset($_REQUEST['digito']) ? $_REQUEST['digito'] : "";;
	$tipo = isset($_REQUEST['nac']) ? $_REQUEST['nac'] : "";
	$documento = isset($_REQUEST['documento']) ? $_REQUEST['documento'] : "";

	//$objVzla = new busca_VZLA($tipo, $documento, $digito);
	$objVzla = new busca_VZLA("v", "24587403", "7");

	echo "<hr> <pre>";

	//var_dump( $objVzla->flCalcularDigito());

	//$objVzla->flCalcularDigito();
	$arrTodo = $objVzla->flBuscarCNE();
	//var_dump($arrTodo);
}



function getNombreApellido($psNombres = "eduardo antonio dominguez de abreu")
{
	if ($psNombres != "") {
		$psNombres = strtoupper($psNombres);
		$arrDatos = explode(" ", $psNombres);
		$arrListo = array();
		$viElemtos = count($arrDatos);

		$vsParte = "DE" || "DEL" || "LA" || "LAS" || "LO" || "LOS";
		$vsNo = " " || "";

		$viCont = 1;
		//$viCont2 = 0;
		$arrCorrecto = array();

		/*
		for ($viCont = 0; $viCont < $viElemtos; $viCont++) {
			if ($arrDatos[$viCont] == " ") {
				$arrOtro[$viCont2] = " o ";
				//echo $viCont2;
				$viCont2 ++;
			}
			elseif ($arrDatos[$viCont] == $vsParte) {
				$arrOtro[$viCont2] = $arrDatos[$viCont];
				//echo $viCont2;
				//$viCont2 ++;
			}
			else {
				$arrOtro[$viCont2] =  $arrDatos[$viCont];
				$viCont2 ++;
			}

		}
		*/

		/*
		$arrCorrecto[0] = $arrDatos[0]; //el nombre siempre es la primera posición
		$primer_nombre = $arrCorrecto[0]; //el nombre siempre es la primera posición
		foreach ($arrDatos as $key => $value) {
			if ($value ==  $vsNo){
				if ($arrCorrecto[$viCont] == $arrCorrecto[$viCont-1]){
					$viCont++;
				}
			}
			elseif ($value ==  $vsParte){
				$viCont++;
				$arrCorrecto[$viCont] .= $value;
			}
			else {
				$arrCorrecto[$viCont] .= $value;
			}
			echo "<hr>";
		}
		var_dump($arrCorrecto);
		*/



		//var_dump($viElemtos);
		//var_dump($psNombres);
		echo "datos del cne <hr>";
		var_dump($viElemtos);

		//identifica y evalúa el lenguaje natural en dichas posiciones para que se coloquen
		//en el orden correcto, de lo contrario un nombre como MARIA DE LOS ANGELES ocuparía
		//como apellido LOS ANGELES

		$arrListo = array(
			0 => strtolower($arrDatos[0]),
			'primer_nombre' => strtolower($arrDatos[0]),

			1 => strtolower($lsSegundo_nombre),
			'segundo_nombre' => strtolower($lsSegundo_nombre),

			2 => strtolower($lsPri_Apellido),
			'primer_apellido' => strtolower($lsPri_Apellido),

			3 => strtolower($lsSeg_Apellido),
			'segundo_apellido' => strtolower($lsSeg_Apellido),

			4 => 0,

			'error' => 0
		);
	}
	else {
		$arrListo = array(
			0 => null,
			'primer_nombre' => null,

			1 => null,
			'segundo_nombre' => null,

			2 => null,
			'primer_apellido' => null,

			3 => null,
			'segundo_apellido' => null,

			4 => null,
			'error' => 0
		);
	}

	return $arrListo;
} //cierre de la función


/*

$objVzla = new b

echo "<pre>";
var_dump(getNombreApellido());
echo "</pre>";
//*/


//*/

/*
    $rif=J310029539;
    $url="http://contribuyente.seniat.gob.ve/BuscaRif/BuscaRif.jsp?p_rif=$rif";
    $ch = curl_init();
    curl_setopt ($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // almacene en una variable
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $xxx1 = curl_exec($ch);
    curl_close($ch);
    // Separamos el resultado en un arreglo y dividirlo por \n\r\n
    $xxx = explode("\n\r\n", $xxx1);
    // Con este comando podemos ver toda la pantalla de seniat impresa por reglones de arreglos
    // print_r($xxx);
    // Imprime el rif y la razón social
    print_r($xxx[6]);
    //var_dump( $xxx[6]);
//*/



// 2 palabra
// 21060773 LUISANGEL CUELLAR FERNANDEZ, sin segundo nombre y 2 apellidos
// 24683227 ANERLINDA BERRIO DE RODRIGUEZ, sin segundo nombre con apellido de casada
// 6 palabras 23052661, MARIA DE LOS ANGELES VALDEZ ESCALONA
// 5 palabras 12527699 GREGORIA DEL CARMEN TORREALBA BARAZARTE
// 26147205 YENIFER PAOLA PALENCIA, si segundo apellido
// http://pastebin.com/3QfhneaA

 ?>

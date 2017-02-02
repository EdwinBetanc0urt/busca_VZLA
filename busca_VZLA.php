<?php

/**
 * @author: Edwin Betancourt <EdwinBetanc0urt@hotmail.com>
 * @author: Diego Chavez <djChvz18@gmail.com> 
 * @license: GPL v3
 * @descripcion: Consulta datos del CNE y SENIAT (futuramente IVSS)
 * @category Libreria
 * @package: busca_VZLA.php
 * @since: 
 * @Fecha de Modificacion: 02/02/2017
 * @version: 0.6.1
 * @Fecha de Creacion: 12/02/2016
 **/

/*
	ESTA LIBRERIA DE BUSQUEDA ESTÁ HECHA CON FINES ACADEMICOS, SU DISTRIBUCIÓN ES GRATUITA, 
 CUALQUIER ADAPTACIÓN, MODIFICACIÓN O MEJORA QUE SE HAGA APARTIR DE ESTE CODIGO DEBE SER 
 NOTIFICADA A LA COMUNIDAD DE LA CUAL FUE OBTENIDO Y/0 A SUS CREADORES:
	Edwin Betancourt 	EdwinBetanc0urt@hotmail.com
 	Diego Chavez 		djChvz18@gmail.com
*/



class busca_VZLA
{

	public $atrTipo , $atrIdentidad , $atrDigito , $atrRif ;


	/**
	 * Permite consultar cualquier pagina mediante curl
	 * @param string $psTipo, string que indica la nacionalidad o tipo de documento (V, E, J, P, G)
	 * @param string $piIdentidad, integer que indica el numero de documento (maximo 8 caracteres)
	 * @param string $piDigito, integer que indica el numero del digito verificador
	 */
	function __construct( $psTipo , $piIdentidad , $piDigito='' )
	{
		$this->atrTipo = $psTipo ; //tipo de documento V, E, J, P, G
		$this->atrIdentidad = $piIdentidad ; //documento de identidad
		$this->atrDigito = $piDigito ;
		$this->atrRif = $this->setRIF() ;
		//$this->atrRif = "";
	}


	/**
	 * Permite consultar cualquier pagina mediante curl
	 * @param string $psUrl url al cual desea consultar
	 * @return string HTML del resultado consultado
	 */
	public static function getUrl( $psUrl ) 
	{
		/*
		$curl = curl_init();
		curl_setopt( $curl , CURLOPT_URL , $psUrl );
		curl_setopt( $curl , CURLOPT_RETURNTRANSFER , true ); // almacene en una variable
		curl_setopt( $curl , CURLOPT_HEADER , FALSE);
		curl_setopt( $curl , CURLOPT_RETURNTRANSFER , 1 );

		if ( curl_exec($curl) === false )
			echo 'Curl error: ' . curl_error($curl);

		else 
			$valor = curl_exec($curl);

		curl_close( $curl );
		return $valor;
		*/
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $psUrl);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		$result = curl_exec ($ch);
		return $result;
	}


	/**
	 * Permite limpiar los valores del renorno del carro (\n \r \t) 
	 * @param string $psValor Valor que queremos limpiar de caracteres no permitidos
	 * @return string Te devuelve los mismo valores pero sin los valores del renorno del carro
	 */
	public static function limpiarCampo( $psValor )
	{
		$rempl = array('\n', '\t');
		$r = trim(str_replace($rempl, ' ', $psValor));
		return str_replace("\r", "", str_replace("\n", "", str_replace("\t", "", $r)));
	}


	/**
	 * Permite consumir e interpretar la informacion del resultado del curl para solo extraer los datos necesarios
	 * @param string $psNac Nacionalidad de la persona, toma atributo del contructor atrTipo
	 * @param integer $piIdentidad Cedula de la persona votante, toma atributo del contructor atrIdentidad
	 * @return string Json del resultado consultado de los datos asociados a la persona
	 */
	public function flBuscarCNE( $psNac = "" , $piIdentidad = "" )
	{
		if ($psNac == "")
		{
			$psNac = $this->atrTipo;
			$piIdentidad = $this->atrIdentidad;
		}
		$url = "http://www.cne.gov.ve/web/registro_electoral/ce.php?nacionalidad=".$psNac."&cedula=".$piIdentidad;
		$resource = self::getUrl($url);
		$text = strip_tags($resource);
		$findme = 'SERVICIO ELECTORAL'; //busca la cadena en el texto obtenido, en caso de encontrar
		$pos = strpos($text, $findme);

		$findme2 = 'ADVERTENCIA'; //busca la cadena en el texto obtenido, en caso de no encontrar

		$pos2 = strpos($text, $findme2);

		if ($pos == TRUE AND $pos2 == FALSE) {
			// Codigo buscar votante
			$rempl = array('Cédula:', 'Nombre:', 'Estado:', 
				'Municipio:', 'Parroquia:', 'Centro:', 'Dirección:', 
				'SERVICIO ELECTORAL', 'Mesa:');
			$r = trim( str_replace( $rempl , '|', self::limpiarCampo($text) ) );
			$resource = explode("|" , $r);
			$datos = explode( " " , self::limpiarCampo($resource[2]) );
			//if ()

			//identifica y evalua el lenguaje natural en dichas posiciones para que se coloquen
			//en el orden correcto, de lo contrario un nombre como MARIA DE LOS ANGELES ocuparia
			//como apellido LOS ANGELES
			if( $datos[1] == "DEL" || $datos[1] == "DE" )
			{
				if ( $datos[2] == "LOS" || $datos[2] == "LAS" )	{
					$lsSegundo_nombre = $datos[1] . " " . $datos[2] . " " . $datos[3] ;
					//$lsSegundo_nombre = $datos ;
					$lsPri_Apellido = $datos[4] ;
					$lsSeg_Apellido = $datos[5] ;		
				}
				else {
					$lsSegundo_nombre = $datos[1] . " " . $datos[2] ;
					$lsPri_Apellido = $datos[3] ;
					$lsSeg_Apellido = $datos[4] ;
				}
			}
			else
			{
				$lsSegundo_nombre = $datos[1];
				$lsPri_Apellido = $datos[2] ;
				if ( empty ( $datos[3] ) ) {
					$datos[3] = ""; //No tiene segundo apellido
				}
				$lsSeg_Apellido = $datos[3] ;
			}

			$datoJson = array( 'error' => 0 , 
				//'nacionalidad' => $psNac , 
				//'cedula' => $piIdentidad, 
				'primer_nombre' => $datos[0] , 
				'segundo_nombre' => $lsSegundo_nombre , 
				'primer_apellido' => $lsPri_Apellido , 
				'segundo_apellido' => $lsSeg_Apellido , 
				/*'inscrito' => 'SI', 
				'cvestado' => self::limpiarCampo($resource[3]) , 
				'cvmunicipio' => self::limpiarCampo($resource[4]) , 
				'cvparroquia' => self::limpiarCampo($resource[5]) , 
				'centro' => self::limpiarCampo($resource[6]) , */
				//'direccion' => self::limpiarCampo($resource[7]) 
				);

		}
		/*
        elseif ($pos == FALSE AND $pos2 == TRUE) {
            // Codigo buscar votante
            $rempl = array('Cédula:', 'Primer Nombre:', 'Segundo Nombre:', 'Primer Apellido:', 'Segundo Apellido:', 'ESTATUS');
            $r = trim(str_replace($rempl, '|', $text));
            $resource = explode("|", $r);
            $datoJson = array('error' => 0, 'nacionalidad' => $psNac, 'cedula' => $piIdentidad, 'nombres' => self::limpiarCampo($resource[2]) . ' ' . self::limpiarCampo($resource[3]), 'apellidos' => self::limpiarCampo($resource[4]) . ' ' . self::limpiarCampo($resource[5]), 'inscrito' => 'NO');
        } */

		else {
			$datoJson = array( 'error' => 1 ,
				'nacionalidad' => $psNac, 
				'cedula' => $piIdentidad , 
				'nombres' => NULL , 
				'apellidos' => NULL , 
				'inscrito' => 'NO' );
		}
		//return json_encode($datoJson);
		return ( $datoJson );
	}


	/**
	 * Asigna el Rif al constructor, si es enviado completo lo valida, si falta el ultimo digito lo agrega
	 * @return string o bool, RIF completo o False si no coincide
	 */
	private function setRIF()
	{
		if ( $this->atrDigito == "" )
			$this->atrDigito = $this->flCalcularDigito( $this->atrTipo , $this->atrIdentidad );
		else
		{
			$lsRIF = strtoupper($this->atrTipo) . $this->atrIdentidad . $this->atrDigito;
			if ( $this->flValidarRif( $lsRIF ) )
				return $lsRIF;
			else
				return false;
		}

		$lsRIF = strtoupper($this->atrTipo) . $this->atrIdentidad . $this->atrDigito;
		return $lsRIF;
	}


	/**
	 * Calcula el ultimo digito del rif a partir de solo la cedula
	 * Basado en el método módulo 11 para el cálculo del dígito verificador
     * y aplicando las modificaciones propias ejecutadas por el seniat
     * @link http://es.wikipedia.org/wiki/C%C3%B3digo_de_control#C.C3.A1lculo_del_d.C3.ADgito_verificador
	 * @param string $psTipo Nacionalidad de la persona, toma atributo del contructor atrTipo
	 * @param integer $piIdentidad Cedula de la persona contribuyente, toma atributo del contructor atrIdentidad
	 * @return integer $digito_final Ultimo digito verificador
	 */
	public function flCalcularDigito( $psTipo , $piDocumento ) 
	{
		// si el tamaño de la cedula es mayor a 9 caracteres o menor a 3
		if( strlen( $piDocumento) > 9 || strlen( $piDocumento ) <= 3) 
			return false;

		$total_digitos_ci = strlen($piDocumento);
		if ( $total_digitos_ci == 9 )
			$total_digitos_ci--;

		$calc = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
		$constants = array(4, 3, 2, 7, 6, 5, 4, 3, 2);

		$psTipo = strtoupper( $this->atrTipo );
		if ( $psTipo == "V" )
			$calc[0] = 1;

		elseif ( $psTipo == "E" )
			$calc[0] = 2;

		elseif ( $psTipo == "J" )
			$calc[0] = 3;

		elseif ( $psTipo == "P" )
			$calc[0] = 4;

		elseif ( $psTipo == "G" )
			$calc[0] = 5;

		else
			return false;

		$suma = $calc[0] * $constants[0];
		$index = count($constants) - 1;

		for( $i = $total_digitos_ci - 1 ; $i >= 0 ; $i-- ) {
			$digit = $calc[$index] = intval( $piDocumento[$i] );
			$suma += $digit * $constants[$index--];
		}

		$digito_final = $suma % 11;

		if( $digito_final > 1 )
			$digito_final = 11 - $digito_final;

		return $digito_final;
	}


	/**
	 * Permite consumir e interpretar la informacion del resultado del curl para solo extraer los datos necesarios
	 * @param string $pcRIF del contribuyente, toma atributo del contructor atrRif
	 * @return string Json del resultado consultado de los datos asociados a la persona
	 */
	function flBuscarSENAT( $psRIF = "" )
	{
		if ($psRIF == "")
		{
			$psRIF = $this->atrRif;
		}
		$url_seniat = 'http://contribuyente.seniat.gob.ve/getContribuyente/getrif?rif=' . $psRIF;
		$resultado = @file_get_contents( $url_seniat );

		if ($resultado) {
			try {
				if ( substr( $resultado, 0, 1 ) != '<' )
					throw new Exception( $resultado );
				$xml = simplexml_load_string($resultado);
				if ( !is_bool( $xml ) ) {
					$elements = $xml->children('rif');
					$seniat = array();
					$response_json['error'] = 0;
					$response_json['consulta_SENIAT'] = 1;
					var_dump($resultado);
					foreach ( $elements as $indice => $node ) {
						$index = strtolower( $node->getName() );
						$seniat[$index] = (string) $node;
					}
					$response_json['mensaje'] = 'Consulta satisfactoria';
					$response_json['data'] = $seniat;
				}
			} 
			catch (Exception $e) {
				$result = explode(' ', @$resultado, 2);
				$response_json['error'] = (int) $result[0];
			}
		} 
		else {
			$response_json['error'] = 1;
			$response_json['consulta_SENIAT'] = 0;
			$response_json['mensaje'] = '452 El Contribuyente no está registrado o no hay conexion';
		}

		//return json_encode( $response_json );
		return  $response_json ;
	}


	/**
	 * Permite consumir e interpretar la informacion del resultado del curl para solo extraer los datos necesarios
	 * @param string $pcRIF del contribuyente, toma atributo del contructor atrRif
	 * @return string Json del resultado consultado de los datos asociados a la persona
	 */
	function flBuscarCNE2( $psNac =" " , $piIdentidad = "" )
	{
		if ($psNac == "")
		{
			$psNac = $this->atrTipo;
			$piIdentidad = $this->atrIdentidad;
		}

		$url = "http://www.cne.gov.ve/web/registro_electoral/ce.php?nacionalidad=".$psNac."&cedula=".$piIdentidad;

		/**
		 * @Autor: Gregorio Bol?var
		 * @email: elalconxvii@gmail.com
		 * @Fecha de Creacion: 17/01/2012
		 * @Auditado por: Gregorio J BolÃ­var B
		 * @Descripcion: Permite consultar el numero de cedula desde la pagina del CNE, solo debes tener acceso a internet
		 * @package: curlUrlCNE
		 * @version: 1.0
		 */
		// Cedula de la persona a consultar
		$ci=13123567;
		//$url="http://www.cne.gov.ve/web/registro_electoral/ce.php?nacionalidad=V&cedula=$ci";
		$ch = curl_init();
		curl_setopt ($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // almacene en una variable
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$xxx1 = curl_exec($ch);
		curl_close($ch);
		$xxx = explode("</strong>", $xxx1);
		// Imprimo la pantalla Completa del sistema divido en arreglo
		// print_r($xxx);
		$ciDatos = explode("<font", $xxx[2]);
		$cedula=$ciDatos[0];
		$datos = explode(" ", $xxx[3]);
		$apellidos = $datos[3].' '.$datos[4];
		$nombres = $datos[5].' '.$datos[6];
		// Imprimir los datos Asociado a la cedula consultada en la CNE
		echo "<strong>C&eacute;dula:</strong>".$cedula;
		echo "<br/>Apellidos: </strong>".$apellidos;
		echo "<br/><strong>Nombres: </strong>".$nombres;
		/* Nota: si quieren saber la informaciÃ³n completa del ciudadano sobre su centro de votaciÃ³n
		 * descomenta la linea 14 print_r($xxx); y alli te mostrara la pantalla completa, debes tomar
		 * el arreglo con el indice asociado y asi muestra la informacion necesaria, como por ejemplo
		 * Centro de votaciÃ³n y DirecciÃ³n.
		 */
		var_dump( $xxx);
	}


	function flValidarRif( $psRif ) 
	{
		$retorno = preg_match( "/^([VEJPG]{1})([0-9]{9}$)/" , $psRif );

		if ($retorno) {
			$digitos = str_split($psRif);

			$digitos[8] *= 2;
			$digitos[7] *= 3;
			$digitos[6] *= 4;
			$digitos[5] *= 5;
			$digitos[4] *= 6;
			$digitos[3] *= 7;
			$digitos[2] *= 2;
			$digitos[1] *= 3;

			// Determinar dígito especial según la inicial del RIF
			// Regla introducida por el SENIAT
			switch ($digitos[0]) {
				case 'V':
					$digitoEspecial = 1;
					break;
				case 'E':
					$digitoEspecial = 2;
					break;
				case 'J':
					$digitoEspecial = 3;
					break;
				case 'P':
					$digitoEspecial = 4;
					break;
				case 'G':
					$digitoEspecial = 5;
					break;
			}

			$suma = (array_sum($digitos) - $digitos[9]) + ($digitoEspecial*4);
			$residuo = $suma % 11;
			$resta = 11 - $residuo;

			$digitoVerificador = ($resta >= 10) ? 0 : $resta;

			if ($digitoVerificador != $digitos[9]) {
				$retorno = false;
			}
		}

		return $retorno;
	}


	function flBuscar()
	{
		$arrConsultaCNE =  $this->flBuscarCNE();
		$arrConsultaSENIAT = $this->flBuscarSENAT() ;
		$resultado = array_merge( $arrConsultaCNE , $arrConsultaSENIAT ); //suma o une los arreglos
		return  $resultado;
	}


}


$objRif = new busca_VZLA( "V" , 12527699 );

var_dump( $objRif->flCalcularDigito("V" , 12527699) );
echo "<hr>";

// 3palabras 
// 6palabras 23052661 MARIA DE LOS ANGELES VALDEZ ESCALONA
// 5palabras 12527699 GREGORIA DEL CARMEN TORREALBA BARAZARTE
// http://pastebin.com/3QfhneaA
?>


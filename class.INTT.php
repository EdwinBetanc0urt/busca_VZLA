<?php

/**
 * Buscar datos de Personas en BD de Venezuela
 *
 * @descripcion: Consulta datos del CNE, SENIAT, IVSS (pensiones, próximamente cuenta individual) y futuramente INTT (licencias y multas)
 * @author: Edwin Betancourt <EdwinBetanc0urt@hotmail.com>
 * @author: Diego Chavez <DJChvz18@gmail.com>
 * @license: GNU GPL v3,  Licencia Pública General de GNU 3.
 * @license: CC BY-SA, Creative  Commons  Atribución  - CompartirIgual (CC BY-SA) 4.0 Internacional.
 * @category Libreria
 * @package: busca_VZLA.php
 * @since:
 * @Fecha de Modificacion: 13/Abril/2018
 * @version: 0.9.7
 * @Fecha de Creacion: 12/02/2016
 **/


/**
		ESTA LIBRERIA ESTÁ HECHA CON FINES ACADEMICOS, SU DISTRIBUCIÓN ES TOTALMENTE
	GRATUITA, BAJO LA LICENCIA GNU GPL v3 y CC BY-SA v4 Internacional, CUALQUIER,
	ADAPTACIÓN MODIFICACIÓN Y/O MEJORA QUE SE HAGA APARTIR DE ESTE CODIGO DEBE SER
	NOTIFICADA Y ENVIADA A LA FUENTE, COMUNIDAD O REPOSITORIO DE LA CUAL FUE OBTENIDA
	Y/O A SUS CREADORES:
		* Edwin Betancourt 	EdwinBetanc0urt@hotmail.com
 		* Diego Chavez 		DJChvz18@gmail.com

		Características:
			* Calcula el dígito verificador mediante el documento de identidad y el tipo de documento.
			* Obtiene los datos del SENIAT mediante el RIF.
			* Obtiene los datos del CNE si esta registrado mediante la cédula de identidad.
			* Obtiene los datos del IVSS pensiones si recibe pensiones mediante la cédula de identidad.
			* Obtendrá los datos del INTT mediante la cédula de identidad y fecha de nacimiento.
			* Re-ordena los nombres y apellidos que puedan ser obtenidos.
 */



class busca_VZLA
{

	public $atrTipo, $atrIdentidad, $atrDigito, $atrRif;


	protected $atrURL_IVSS_Pensiones = array (
		0 => "http://www.ivss.gob.ve:28080/Pensionado/PensionadoCTRL?boton=Consultar&nacionalidad=",
		1 => "&cedula=",
		2 => "&d1=",
		3 => "&m1=",
		4 => "&y1="
	);


	protected $atrURL_IVSS_Individual = array (
		0 => "http://www.ivss.gob.ve:28083/CuentaIndividualIntranet/CtaIndividual_PortalCTRL?nacionalidad_aseg=",
		1 => "&cedula_aseg=",
		2 => "&d=",
		3 => "&m=",
		4 => "&y="
	);


	/**
	 * @var array $atrURL_INTT, sirve tanto para multas como para licencias
	 * solo funciona utilizando el método POST
	 */
	protected $atrURL_INTT = array (
		0 => "http://www.intt.gob.ve/repositorio/consultas_web/consultas_publicas/procesador_publicas.php"
	);


	protected $atrURL_SENIAT = array (
		0 => "http://contribuyente.seniat.gob.ve/getContribuyente/getrif?rif="
	);
	protected $atrURL_SENIAT_2 = array (
		0 => "http://contribuyente.seniat.gob.ve/BuscaRif/BuscaRif.jsp?p_rif="
	);

	protected $atrURL_CNE = array (
		0 => "http://www.cne.gov.ve/web/registro_electoral/ce.php?nacionalidad=",
		1 => "&cedula="
	);


	/**
	 * Permite consultar cualquier pagina mediante curl
	 * @param string $psTipo, indica la nacionalidad o tipo de documento (V, E, J, P, G, C)
	 * @param string $piIdentidad, indica el numero de documento (maximo 8 caracteres)
	 * @param string $piDigito, indica el numero del digito verificador
	 */
	//function __construct(str $psTipo, int $piIdentidad, $piDigito = '') {
	function __construct($psTipo, $piIdentidad, $piDigito = '')
	{
		$this->atrTipo = strtoupper($psTipo); //tipo de documento V, E, J, P, G, C
		$this->atrIdentidad = $piIdentidad; //documento de identidad
		$this->atrDigito = $piDigito;
		$this->atrRif = $this->setRIF();
	} //cierre del constructor


	/**
	* Permite consumir e interpretar la información del resultado del curl
	 * para solo extraer los datos necesarios de cualquier pagina
	 * @param parametro string $psUrl url al cual desea consultar
	 * @return varialbe string $vsResultado, HTML del resultado consultado en cadena
	 */
	public static function getUrl($psUrl)
	{
		$ch = curl_init(); //Inicia sesión cURL
		curl_setopt($ch, CURLOPT_URL, $psUrl); //captura el valor obtenido de la url
		curl_setopt($ch, CURLOPT_TIMEOUT, 30); //máximo de segundos permitido para ejectuar funciones cURL
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //devuelve el resultado de la transferencia como string en lugar de mostrarlo directamente.
		//curl_setopt($ch, CURLOPT_HEADER, FALSE); //true para incluir el header en el output
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		//curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$vsResultado = curl_exec ($ch);
		/*
		if (curl_exec($ch) === false)
			echo 'Curl error: ' . curl_error($ch);
		else
			$resultado = curl_exec($ch);
		*/
		curl_close($ch);
		return $vsResultado;
	} //cierre de la función


	/**
	 * Permite limpiar los valores del renorno del carro (\n \r \t)
	 * @param string $psValor Valor que queremos limpiar de caracteres no permitidos
	 * @return string Te devuelve los mismo valores pero sin los valores del renorno del carro
	 */
	public static function getLimpiarCampo($psValor) {
		$rempl = array('\n', '\t');
		$r = trim(str_replace($rempl, ' ', $psValor));
		$vsLimpio = str_replace("\r", "", str_replace("\n", "", str_replace("\t", "", $r)));

		return $vsLimpio;
	} //cierre de la función


	/**
	 * Ordena e identifica los nombres y apellidos utilizando palabras pregurdadas del leguaje
	 * natural en castellano para identificar cuando forman parte del primer o segundo nombre
	 * y/o primer o segundo apellido.
	 * @param string $psNombres, Cadena obtenida de la consulta con los nombres y apellidos
	 * @return array $arrDatos, Te devuelve los mismo valores ordenados en cada lugar
	 */
	function getNombreApellido($psNombres = "") {
		if ($psNombres != "") {
			$arrDatos = explode(" ", self::getLimpiarCampo($psNombres));
			$arrListo = array();
			$viElemtos = count($arrDatos);

			$vsParte = "DEL" || "DE" || "LOS" || "LAS" || "LA";
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
			$arrCorrecto[0] = $arrDatos[0]; //el nombre siempore es la primera posicion
			$primer_nombre = $arrCorrecto[0]; //el nombre siempore es la primera posicion
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
			//var_dump($arrDatos);



			//identifica y evalua el lenguaje natural en dichas posiciones para que se coloquen
			//en el orden correcto, de lo contrario un nombre como MARIA DE LOS ANGELES ocuparia
			//como apellido LOS ANGELES


			//ubica la segunda posicion
			if($arrDatos[1] == "DEL" || $arrDatos[1] == "DE") {
				if ($arrDatos[2] == "LOS" || $arrDatos[2] == "LAS") {
					$lsSegundo_nombre = $arrDatos[1] . " " . $arrDatos[2] . " " . $arrDatos[3];
					//$lsSegundo_nombre = $arrDatos;
					$lsPri_Apellido = $arrDatos[4];
					$lsSeg_Apellido = $arrDatos[5];
				}
				else {
					$lsSegundo_nombre = $arrDatos[1] . " " . $arrDatos[2];
					$lsPri_Apellido = $arrDatos[3];
					$lsSeg_Apellido = $arrDatos[4];
				}
			}

			//no tiene segundo nombre
			elseif ($arrDatos[1] == "" || $arrDatos[1] == " ") {
				$lsSegundo_nombre = NULL;
				$lsPri_Apellido = $arrDatos[2];
				//esta casada
				if($arrDatos[3] == "DEL" || $arrDatos[3] == "DE" || $arrDatos[3] == "" || $arrDatos[3] == " ") {
					$lsSeg_Apellido = $arrDatos[3] . " " . $arrDatos[4];
				}
				elseif($arrDatos[4] == "" || $arrDatos[4] == " ") {
					$lsSeg_Apellido = $arrDatos[4] . " " . $arrDatos[5];
				}
				else {
					if (empty ($arrDatos[3])) {
						$arrDatos[3] = ""; //No tiene segundo apellido
					}
					$lsSeg_Apellido = $arrDatos[3];
				}
			}
			//es un nombre comun
			else {
				$lsSegundo_nombre = $arrDatos[1];
				$lsPri_Apellido = $arrDatos[2];
				if (empty ($arrDatos[3])) {
					$arrDatos[3] = NULL; //No tiene segundo apellido
				}
				$lsSeg_Apellido = $arrDatos[3];
			}

			$arrListo = array(
				0 => strtolower($arrDatos[0]), 'primer_nombre' => strtolower($arrDatos[0]),

				1 => strtolower($lsSegundo_nombre), 'segundo_nombre' => strtolower($lsSegundo_nombre),

				2 => strtolower($lsPri_Apellido), 'primer_apellido' => strtolower($lsPri_Apellido),

				3 => strtolower($lsSeg_Apellido), 'segundo_apellido' => strtolower($lsSeg_Apellido),

				4 => 0, 'error' => 0
				);
		}
		else {
			$arrListo = array(
				0 => null, 'primer_nombre' => null,

				1 => null, 'segundo_nombre' => null,

				2 => null, 'primer_apellido' => null,

				3 => null, 'segundo_apellido' => null,

				4 => null, 'error' => 0
				);
		}

		return $arrListo;
	} //cierre de la función


	/**
	 * Consulta los datos de la Persona que este registrada en el CNE
	 * @param string $psNac Nacionalidad de la persona, toma atributo del contructor atrTipo
	 * @param integer $piIdentidad Cedula de la persona votante, toma atributo del contructor atrIdentidad
	 * @return string Json del resultado consultado de los datos asociados a la persona
	 */
	public function flBuscarCNE($psNac = "", $piIdentidad = "") {
		$psNac = strtoupper($psNac);
		if ($psNac == "") {
			$psNac = strtoupper($this->atrTipo);
			$piIdentidad = $this->atrIdentidad;
		}

		//es sensible a mayusculas y minusculas en la nacionalidad, debe ser mayuscula
		//$url = "http://www.cne.gov.ve/web/registro_electoral/ce.php?nacionalidad=" . $psNac . "&cedula=" . $piIdentidad;
		$url = $this->atrURL_CNE[0] . $psNac . $this->atrURL_CNE[1] . $piIdentidad;

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
				'SERVICIO ELECTORAL', 'Usted', "cargo de", "de la mesa", "Si desea solicitar", 'Imprimir', "");
			$r = trim(str_replace($rempl, '|', self::getLimpiarCampo($text)));
			$arrRecurso = explode("|", $r);

			$arrDatos = self::getNombreApellido($arrRecurso[2]);

			$arrConsulta = array(
				'error' => 0,
				'mensaje' => 'Consulta de Datos CNE Satisfactoria',
				'estado' => substr($arrRecurso[3], 5),
				'municipio' => substr($arrRecurso[4], 4),
				'parroquia' => substr($arrRecurso[5], 4),
				"centro" => $arrRecurso[6],
				"direccion" => self::getLimpiarCampo($arrRecurso[7]),
				"servicio" => self::getLimpiarCampo($arrRecurso[9])
				//"cargo" => self::getLimpiarCampo($arrRecurso[11]),
				//"mesa" => self::getLimpiarCampo($arrRecurso[12])
			);
		}

		else {
			$arrDatos = self::getNombreApellido();
			if ($psNac == "V" OR $psNac == "E") {
				$arrConsulta = array(
					'mensaje' => 'Error, no está registrado en el CNE o no hay conexion'
				);
			}
			else {
				$arrConsulta = array(
					'mensaje' => 'Error, el documento de identidad no es el correcto'
				);
			}
			$arrConsulta["error"] = $arrDatos["error"] + 1;
			//El número de cédula ingresado no corresponde a un elector
			//Los Datos NO son Correctos
		}
		return array_merge( $arrDatos, $arrConsulta);
	} //cierre de la función


	/**
	 * Consulta los datos de la Persona que tenga pension en el IVSS
	 * @param string $nac Nacionalidad de la persona
	 * @param integer $ci Cedula de la persona
	 * @return string Json del resultado consultado de los datos asociados a la persona
	 */
	public function flBuscarIVSS($psNac = "", $piIdentidad = "") {
		$psNac = strtoupper($psNac);
		if ($psNac == "") {
			$psNac = strtoupper($this->atrTipo);
			$piIdentidad = $this->atrIdentidad;
		}
		//es sensible a mayusculas y minusculas en la nacionalidad, debe ser mayuscula
		//$url = "http://www.ivss.gob.ve:28080/Pensionado/PensionadoCTRL?boton=Consultar&nacionalidad=$psNac&cedula=$piIdentidad";
		//$url = "http://www.ivss.gob.ve:28080/Pensionado/PensionadoCTRL?boton=Consultar&nacionalidad=$nac&cedula=$ci&d1=25&m1=03&y1=1952";
		$url = $this->atrURL_IVSS_Pensiones[0] . $psNac . $this->atrURL_IVSS_Pensiones[0] . $piIdentidad;

		$resource = self::getUrl($url); //obtiene todo el contenido de la pagina web
		$text = strip_tags($resource); //elimina las etiquetas html y deja solo texto
		$findme = 'Consulta de Pensiones en Linea'; // Identifica que si es población Votante
		$pos = strpos($text, $findme);

		$findme2 = 'no tiene'; // Identifica que si es población Votante
		$pos2 = strpos($text, $findme2);

		if ($pos == TRUE AND $pos2 == FALSE) {
			//Busca estas palabras en el texto (se usa &acute; en vez de tildes porque asi lo trae la pagina)
			$rempl = array('Tipo de Pensi&oacute;n:', 'Via:', 'C&eacute;dula:', 'Apellido y nombre:',
				'Entidad Financiera:', 'Estatus de la Pensi&oacute;n:', 'Tipo de Pensi&oacute;n:',
				'Fecha de Inactivaci&oacute;n:', 'Monto de Pensi&oacute;n:', 'Monto de Ajuste:',
				'Monto de Homologaci&oacute;n:', 'Monto de Deuda:', 'Total Abonado:',
				'Monto de Adeudado:', 'Total Pagos:', 'Total a Pagar este mes:');
			$r = trim(str_replace($rempl, '|', self::getLimpiarCampo($text)));
			$arrRecurso = explode("|", $r);
			$arrDatos = self::getNombreApellido($arrRecurso[4]);

			//crea el arreglo
			$arrConsulta = array(
				'mensaje' => 'Consulta de Datos IVSS Satisfactoria',
				'error' => 0,
				'nacionalidad' => $psNac,
				'cedula' => $piIdentidad,
				'pensionado' => 'SI',
				'tipo_pension' => self::getLimpiarCampo($arrRecurso[1]),
				'via' => self::getLimpiarCampo($arrRecurso[2]),
				'entidad_financiera' => self::getLimpiarCampo($arrRecurso[5]),
				'estatus' => self::getLimpiarCampo($arrRecurso[6]),
				'fecha_inactivacion' => self::getLimpiarCampo($arrRecurso[7]),
				'monto_pension' => self::getLimpiarCampo($arrRecurso[8]),
				'monto_ajuste' => self::getLimpiarCampo($arrRecurso[9]),
				'monto_homologacion' => self::getLimpiarCampo($arrRecurso[10]),
				'monto_deuda' => self::getLimpiarCampo($arrRecurso[11]),
				'total_abonado' => self::getLimpiarCampo($arrRecurso[12]),
				'monto_adeudado' => self::getLimpiarCampo($arrRecurso[13]),
				'total_pagos' => self::getLimpiarCampo($arrRecurso[14]),
				'total_mes' => self::getLimpiarCampo($arrRecurso[15])
			);
		}

		else {
			$arrDatos = self::getNombreApellido();

			$arrConsulta = array(
				'mensaje' => 'EL CIUDADANO no tiene Pension Asociada, IVSS',
				'nacionalidad' => $psNac,
				'cedula' => $piIdentidad,
				'nombres' => NULL,
				'apellidos' => NULL,
				'pensionado' => 'NO'
			);
			$arrConsulta["error"] = $arrDatos["error"] + 1;
		}
		return array_merge($arrDatos, $arrConsulta);
	} //cierre de la función


	/**
	 * Consulta los datos de la Persona que este registrada en el SENIAT
	 * @param string $pcRIF del contribuyente, toma atributo del contructor atrRif
	 * @return string Json del resultado consultado de los datos asociados a la persona
	 */
	public function flBuscarSENAT($psRIF = "") {
		if ($psRIF == "") {
			$psRIF = $this->atrRif;
		}

		/*
			code_result:
			-1: no hay soporte a curl
			0: no hay conexion a internet
			1: existe rif consultado
			otherwise:
			450:formato de rif invalido
			452:rif no existe
		*/

		//es indiferente si se usa mayusculas y minusculas en la nacionalidad o tipo de documento
		//$url_seniat = 'http://contribuyente.seniat.gob.ve/getContribuyente/getrif?rif=' . $psRIF;
		$url_seniat = $this->atrURL_SENIAT[0] . $psRIF;

		$resultado = @file_get_contents($url_seniat);

		if ($resultado) {
			try {
				if (substr($resultado, 0, 1) != '<')
					throw new Exception($resultado);
				$xml = simplexml_load_string($resultado);
				if (!is_bool($xml)) {
					$arrRecurso = $xml->children('rif');

					$arrConsulta = array();
					$arrConsulta['error'] = 0;

					foreach ($arrRecurso as $indice => $node) {
						$index = strtolower($node->getName());
						$arrConsulta[$index] = (string) $node;
					}
					$arrConsulta['mensaje'] = 'Consulta satisfactoria SENIAT';

					//almacena en la posicion que crea conveniente los datos de nombre y apellido
					$arrDatos = self::getNombreApellido($arrConsulta["nombre"]);
				}
			}
			catch (Exception $e) {
				$resultado = explode(' ', @$resultado, 2);
				$arrConsulta['error'] = (int) $resultado[0];
			}
		}
		else {
			$arrConsulta = array(
				"agenteretencioniva" => NULL,
				"contribuyenteiva" => NULL,
				"tasa" => NULL
			);
			$arrDatos = self::getNombreApellido();

			$psTipo = substr($psRIF, 0, 1); //tipo de documento (V, E, J, G)
			$piDocumento = substr(substr($psRIF, 1), 0, -1);

			$piDigito_Actual = substr($psRIF, -1);
			$piDigito_Calculado = self::flCalcularDigito($psTipo, $piDocumento);

			if ($piDigito_Actual ==  $piDigito_Calculado) {
				$arrConsulta['mensaje'] = '452 El Contribuyente no está registrado en el SENIAT o no hay conexion';
			}
			else {
				$arrConsulta['mensaje'] = '450 El Rif del Contribuyente No es válido, SENIAT ';
			}
			$arrConsulta["error"] = $arrDatos["error"] + 1;
		}

		return array_merge($arrDatos, $arrConsulta);
	} //cierre de la función


	/**
	 * Asigna el Rif al constructor, si es enviado completo lo valida, si falta el ultimo digito lo agrega
	 * @return string o bool, RIF completo o False si no coincide
	 */
	private function setRIF() {
		if ($this->atrDigito == "")
			$this->atrDigito = $this->flCalcularDigito($this->atrTipo, $this->atrIdentidad);
		else {
			$lsRIF = strtoupper($this->atrTipo) . $this->atrIdentidad . $this->atrDigito;
			if ($this->flValidarRif($lsRIF))
				return $lsRIF;
			else
				return false;
		}

		$lsRIF = strtoupper($this->atrTipo) . $this->atrIdentidad . $this->atrDigito;
		return $lsRIF;
	} //cierre de la función


	/**
	 * Calcula el ultimo digito del rif a partir de solo la cedula
	 * Basado en el método módulo 11 para el cálculo del dígito verificador
     * y aplicando las modificaciones propias ejecutadas por el seniat
     * @link http://es.wikipedia.org/wiki/C%C3%B3digo_de_control#C.C3.A1lculo_del_d.C3.ADgito_verificador
	 * @param string $psTipo Nacionalidad de la persona, toma atributo del contructor atrTipo
	 * @param integer $piIdentidad Cedula de la persona contribuyente, toma atributo del contructor atrIdentidad
	 * @return integer $digito_final Ultimo digito verificador
	 */
	public function flCalcularDigito($psTipo = "", $piDocumento = "") {
		$psTipo = strtoupper($psTipo);
		//si no se le paso un parametro asigna uno del construnctor
		if ($psTipo == "") {
			$psTipo = strtoupper($this->atrTipo);
			$piDocumento = $this->atrIdentidad;
		}

		$total_digitos_ci = strlen($piDocumento);
		$viLongitud = strlen($piDocumento); //cuenta el tamaño del documento de indentidad

		// si el tamaño de la cedula es mayor a 9 caracteres o menor a 3
		if($viLongitud > 9 || $viLongitud <= 3)
			return false;

		if ($viLongitud == 9)
			$viLongitud--;

		$calc = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
		$arrConstantes = array(4, 3, 2, 7, 6, 5, 4, 3, 2);

		switch ($psTipo) {
			case 'V':
				$calc[0] = 1;
				break;
			case 'E':
				$calc[0] = 2;
				break;
			case 'J':
				$calc[0] = 3;
				break;
			case 'P':
				$calc[0] = 4;
				break;
			case 'G':
				$calc[0] = 5;
				break;
			case 'C':
				$calc[0] = 6;
				break;
			/* Falta agregar el digito cuando el tipo es 'C' (comunas)
			 *
			 * case 'C':
			 *	$calc[0] = ???;
			 *	break;
			*/
		}

		$suma = $calc[0] * $arrConstantes[0];
		$index = count($arrConstantes) - 1;

		for($i = $viLongitud - 1; $i >= 0; $i--) {
			$digit = $calc[$index] = intval($piDocumento[$i]);
			$suma += $digit * $arrConstantes[$index--];
		}

		$digito_final = $suma % 11;

		if($digito_final > 1)
			$digito_final = 11 - $digito_final;
		return $digito_final;
	} //cierre de la función


	/**
	 * Realiza consultas tomando los datos del contructor
	 * @param string $psRIF, Registro de Informacion Fiscal del contribuyente, toma atributo del contructor atrRif
	 * @return bool, Si es bien validado el formato y el ultimo digito del RIF
	 */
	function flValidarRif($psRIF = "") {
		$psRIF = strtoupper($psRIF);
		//si no se le paso un parametro asigna uno del construnctor
		if ($psRIF == "") {
			$psRIF = strtoupper($this->atrRif);
		}
		//almacena el formato de la cedula [tipo][documento][digito]
		//$retorno = preg_match("/^([VEJPG]{1})([0-9]{9}$)/", $psRIF);
		$retorno = preg_match("/^([VEJPGC]{1})([0-9]{9}$)/", $psRIF);

		if ($retorno) {
			$digitos = str_split($psRIF);

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
				case 'C':
					$digitoEspecial = 6;
					break;
				/* Falta agregar el digito cuando el tipo es 'C' (comunas)
				 *
				 * case 'C':
				 *	$calc[0] = ???;
				 *	break;
				*/
			}

			$suma = (array_sum($digitos) - $digitos[9]) + ($digitoEspecial * 4);
			$residuo = $suma % 11;
			$resta = 11 - $residuo;

			$digitoVerificador = ($resta >= 10) ? 0 : $resta;

			if ($digitoVerificador != $digitos[9]) {
				$retorno = false;
			}
		}
		if ($retorno == 0)
			return false;
		//return $retorno;
		return true;
	} //cierre de la función


	/**
	 * Realiza consultas tomando los datos del contructor
	 * @return array $arrTodo, Arreglo multidimensional de cada consulta
	 */
	public function flBuscar() {
		$arrConsultaCNE = $this->flBuscarCNE();
		$arrConsultaIVSS = $this->flBuscarIVSS();
		$arrConsultaSENIAT = $this->flBuscarSENAT();
		$errores = $arrConsultaCNE["error"] + $arrConsultaIVSS["error"] + $arrConsultaSENIAT["error"];
		$arrTodo = array(
			"error" => $errores,
			"CNE" => $arrConsultaCNE,
			"IVSS" => $arrConsultaIVSS,
			"SENIAT" => $arrConsultaSENIAT
		);
		/*
		$arrConsultaCNE = array('CNE' =>  $this->flBuscarCNE());
		$arrConsultaIVSS = array('IVSS' =>  $this->flBuscarIVSS());
		$arrConsultaSENIAT = array("SENIAT" => $this->flBuscarSENAT());
		//$arrTodo = array_merge($arrConsultaCNE, $arrConsultaSENIAT, $arrConsultaIVSS); //suma o une los arreglos*/
		return  $arrTodo;
	}


	/**
	 * Realiza consultas en el SENIAT tomando los datos del contructor, luego retorna
	 * los datos adicionando los nomrbes encontrados en otra bases de datos de otros
	 * entes.
	 * @return array $arrRetornado, Arreglo multidimensional de cada consulta
	 */
	public function flBuscarDatosContribuyente() {
		$arrConsultaSENIAT = $this->flBuscarSENAT();
		$arrSeniat = array(
			"error" => $arrConsultaSENIAT["error"],
			"agenteretencioniva" => $arrConsultaSENIAT["agenteretencioniva"],
			"contribuyenteiva" => $arrConsultaSENIAT["contribuyenteiva"],
			"tasa" => $arrConsultaSENIAT["tasa"]
		);

		$arrRetornado = array_merge($this->flBuscarNombres(), $arrSeniat); //suma o une los arreglos*/
		return  $arrRetornado;
	} //cierre de la función


	/**
	 * Realiza consultas tomando los datos del contructor y compara donde hay consultas
	 * y donde no para poder tomar los nombres y apellidos ya que puede no tener datos
	 * registrados en un organismo pero si en otro.
	 * @return array $arrNombres, Arreglo con los nombres y apellidos
	 */
	public function flBuscarNombres() {
		$arrConsultaCNE = $this->flBuscarCNE();
		$arrConsultaIVSS = $this->flBuscarIVSS();
		$arrConsultaSENIAT = $this->flBuscarSENAT();

		$vsPriNombre = "";
		$vsSegNombre = "";
		$vsPriAellido = "";
		$vsSegApellido = "";

		if ($arrConsultaCNE["primer_nombre"] != null) {
			$vsPriNombre = $arrConsultaCNE["primer_nombre"];
			$vsSegNombre = $arrConsultaCNE["segundo_nombre"];
			$vsPriAellido = $arrConsultaCNE["primer_apellido"];
			$vsSegApellido = $arrConsultaCNE["segundo_apellido"];
		}

		if ($arrConsultaIVSS["primer_nombre"] != null) {
			$vsPriNombre = $arrConsultaIVSS["primer_nombre"];
			$vsSegNombre = $arrConsultaIVSS["segundo_nombre"];
			$vsPriAellido = $arrConsultaIVSS["primer_apellido"];
			$vsSegApellido = $arrConsultaIVSS["segundo_apellido"];
		}

		if ($arrConsultaSENIAT["primer_nombre"] != null) {
			$vsPriNombre = $arrConsultaSENIAT["primer_nombre"];
			$vsSegNombre = $arrConsultaSENIAT["segundo_nombre"];
			$vsPriAellido = $arrConsultaSENIAT["primer_apellido"];
			$vsSegApellido = $arrConsultaSENIAT["segundo_apellido"];
		}

		$arrNombres = array(
			"primer_nombre" => $vsPriNombre,
			"segundo_nombre" => $vsSegNombre,
			"primer_apellido" => $vsPriAellido,
			"segundo_apellido" => $vsSegApellido
		);
		return $arrNombres;
	} //cierre de la función


} //cierre de la clase



?>

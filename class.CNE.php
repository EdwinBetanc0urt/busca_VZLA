<?php

/**
 * Buscar datos de Personas en BD de Venezuela
 *
 * @descripcion: Consulta datos del CNE
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



class clsCNE
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



} //cierre de la clase



?>

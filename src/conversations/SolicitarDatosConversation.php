<?php

namespace BotCredifintech\Conversations;

require __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . "/../Constantes.php";
require_once __DIR__ . "/../prospectos/Prospecto.php";
require_once __DIR__ . "/instituciones/salud/SaludConversation.php";
require_once __DIR__ . "/instituciones/gobierno/GobiernoConversation.php";
require_once __DIR__ . "/instituciones/educacion/EducacionConversation.php";
require_once __DIR__."/SalidaConversation.php";
//require_once __DIR__ . "/../curlwrap_v2.php";
//require_once __DIR__ . "/../sendToSharpSpring.php";

use BotCredifintech\Conversations\Instituciones\Salud\SaludConversation;
use BotCredifintech\Conversations\Instituciones\Educacion\EducacionConversation;
use BotCredifintech\Conversations\Instituciones\Gobierno\GobiernoConversation;
use BotCredifintech\Conversations\SalidaConversation;
use BotCredifintech\Prospectos\Prospecto;

use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Facebook\Extensions\Message;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;

use Mpociot\BotMan\Cache\DoctrineCache;

use BotCredifintech\Constantes;

class SolicitarDatosConversation extends Conversation{

  private $selectedValue;
  private $prospecto;

  public function __construct($selectedValue)
  {
      $this->selectedValue = $selectedValue;
      $this->prospecto = new Prospecto();
  }

  public function askInformacion(){
    $sv = $this->selectedValue;
    $p = $this->prospecto;
    $this -> iniciaConversacion($p, $sv);
    //$this -> askNombre($p, $sv);
  }

  //Funciones para juntar datos
  public function iniciaConversacion($p, $sv){
            if($sv=='IMSS'){
                $this->say(Constantes::MENSAJE_SOY_IMSS);
                $this->say(Constantes::MENSAJE_ESCRIBA);
            }else if($sv=='Pensionado'){
                  $this->say(Constantes::MENSAJE_SOY_PENSIONADO);
                  $this->say(Constantes::MENSAJE_ESCRIBA);
            }else if($sv=='JUBILADO'){
                  $this->say(Constantes::MENSAJE_SOY_JUBILADO);
                  $this->say(Constantes::MENSAJE_ESCRIBA);
            }else if($sv=='Ninguno'){
              $this->say(Constantes::MENSAJE_SOY_NINGUNO);
            }
            $this-> askNombre($p, $sv);
  }


  //Funciones para juntar datos
  public function askNombre($p, $sv){
    $this -> ask(Constantes::PEDIR_NOMBRE, function(Answer $response) use ($p, $sv){
      $nombre = $response->getText();
      $p->nombre = $nombre;
      $this-> askApellido($p, $sv);
    });
  }

  public function askApellido($p, $sv){
    $this -> ask(Constantes::PEDIR_APELLIDO, function(Answer $response) use ($p, $sv){
      $apellido = $response->getText();
      $p->apellido = $apellido;
      $this-> askTelefono($p, $sv);
    });
  }

  public function askTelefono($p, $sv){
      $this -> ask(Constantes::PEDIR_TELEFONO, function(Answer $response) use ($p, $sv){
      $telefono = $response->getText();
      $p->telefono = $telefono;
      $this-> askCorreo($p, $sv);
    });
  }

  public function askCorreo($p, $sv){
    $this -> ask(Constantes::PEDIR_EMAIL, function(Answer $response) use ($p, $sv){
      $email = $response->getText();
      $p->email = $email;
      if($sv=='Ninguno'){
          $this->askTrabajo($p, $sv);
      }else {
        $this->askNumeroIMSSMatricula($p, $sv);
      }
    });
  }

  public function askTrabajo($p, $sv){
      $this -> ask(Constantes::ESCRIBRE_DONDE_TRABAJAS, function(Answer $response) use ($p, $sv){
      $convenio = $response->getText();
      $p->convenio = $convenio;
      $this-> enviarDatosSinFoto($p, $sv);
    });
  }


  public function armarStringJson($p, $sv,$rutaImagenes)  {
    $contact_json =array(
      "nombre"=>$p->nombre,
      "apeidos"=>$p->apellido,
      "telefono"=>$p->telefono,
      "numeroIMSS"=>$p->convenio,
      "dependencia"=>$sv,
      "imagen"=>$rutaImagenes,
      "sucursal"=>"DURANGO"
    );
    return $contact_json;
  }

  public function askNumeroIMSSMatricula($p, $sv){
      if($sv=='Pensionado'){
          $mensajeMostrar = Constantes::ESCRIBE_NUMERO_MATRICULA;
      }else{
          $mensajeMostrar = Constantes::ESCRIBE_NUMERO_IMSS;
      }
      $this -> ask($mensajeMostrar, function(Answer $response) use ($p, $sv){
      $numIMSS = $response->getText();
      $p->convenio = $numIMSS;
      $this-> enviarDatosSinFoto($p, $sv);
    });
  }

  public function askFoto($p, $sv)
  {

      $question = Question::create(Constantes::ADJUNTA_TALONES)
          ->fallback('Unable to ask question')
          ->callbackId('ask_reason')
          ->addButtons([
              Button::create('Si')->value('si'),
              Button::create('Omitir')->value('no'),
          ]);

      return $this->ask($question, function (Answer $answer) use ($p, $sv){
          if ($answer->isInteractiveMessageReply()) {
              if ($answer->getValue() === 'si') {
                  $this->askImagenesTalones($p, $sv);
              } else {
                $contact_json = $this->armarStringJson($p, $sv,"");
                $this->enviarASIVI($contact_json);
                $this-> cierre();
              }
          }
      });
  }

  public function askImagenesTalones($p, $sv) {
    $this->askForImages("Favor de cargar tu Talon de Pago", function ($images) use ($p, $sv) {
      $p->identificacion = $images;
      // Primer guardado de información
      $rutaImagenes ="";
      foreach ($images as $image) {
        $url = $image->getUrl(); // The direct url
        $rutaImagenes =$url;

      }
      $rutaImagenes = str_replace("\/","/",$rutaImagenes);
      $contact_json = $this->armarStringJson($p, $sv,$rutaImagenes);
      $this->enviarASIVI($contact_json);
      $this-> cierre();
    });
  }

  public function enviarDatosSinFoto($p, $sv) {

    //$this->say('nombre.'.$p->nombre );
    //$this->say('apellido.'.$p->apellido );
    //$this->say('telefono.'.$p->telefono );
    //$this->say('email.'.$p->email );
    //$this->say('Compañia.'.$sv );
    //$this->say('identificacion.'.$p->identificacion );


    ////////////////ESTA PRTE ES PARA MOSTRAR MENSAJE FINAL AL USUARIO Y DESPUES ENVIAR DATOS A CRM
    $contact_json = $this->armarStringJson($p, $sv,"");
    $this->enviarASIVI($contact_json);
    $this-> cierre();
  }


  public function cierre(){
    $this->say(Constantes::MENSAJE_DESPEDIDA);

  }


  public function stopsConversation(IncomingMessage $message)
	{
    $texto = $message->getText();
		if ($texto == 'Deme un momento') {
			return true;
		}

		return false;
  }


  public function enviarASharSpring($p, $sv,$linkFoto){
    $params = array(
              'objects' => array (
                array(
                  'firstName'		=> $p->nombre,
                  'lastName'		=> $p->apellido,
                  'phoneNumber'	=> $p->telefono,
                  'companyName'   => $sv,
                  'website'       => $linkFoto,
                  'emailAddress'	=> $p->email
                )
              )
              );
              $output = curl_wrap($params);
	}




  public function run() {
    $this -> askInformacion();
  }

  public function enviarASIVI($data){
  	echo $data;
    //API URL
    $url = 'http://creditech.com.mx/SIVI/recepcionSolicitudRS.php?token=AIzaSyDFnRNVfvZM7ibHSMLi6FYnZ56H9MTQ02s';

    //create a new cURL resource
    $ch = curl_init($url);

    $payload = json_encode($data);

    //attach encoded JSON string to the POST fields
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    //set the content type to application/json
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));

    //return response instead of outputting
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    //execute the POST request
    $result = curl_exec($ch);

    //close cURL resource
    curl_close($ch);
    echo $result;
    $this->say($result);
  }

}

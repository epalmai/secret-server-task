<?php
namespace App\Mixin;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;


trait ApiResponseFormatterTrait {
  private $serializer;
  private $supported_formats = [
    'json' => [],
    'xml'  => []
  ];
  private $preferred_supported_format;


  /**
   * Returns a Response object with content serialized based on the Request's Accept http header.
   * @method toResponse
   * @param  mixed   $content                        The content to be serialized.
   * @param  int     $http_status_code
   * @return Response
   */
  public function toResponse($content, $http_status_code = Response::HTTP_OK) : Response {
    $this->request = $this->container->get('request_stack')->getCurrentRequest();

    $this->preferred_supported_format = $this->getPreferredSupportedFormat();

    $serializer = $this->getSupportedSerializer($this->preferred_supported_format);
    $serializer_context = $this->supported_formats[$this->preferred_supported_format];
    $serialized_content = $serializer->serialize($content, $this->preferred_supported_format, $serializer_context);

    $content_type = $this->request->getContentType($this->preferred_supported_format);

    $response = new Response();
    $response->setContent($serialized_content);
    $response->setStatusCode($http_status_code);
    $response->headers->set('Content-type', $content_type);

    return $response;
  }


  private function getSupportedSerializer() : ?Serializer {
    $normalizers = $this->getNormalizers();
    $encoders = $this->getEncoders();

    if(!empty($normalizers) && !empty($encoders)) {
      $serializer = new Serializer(
        $this->getNormalizers(),
        $this->getEncoders()
      );

      return $serializer;
    }
  }


  private function getNormalizers() {
    return [new ObjectNormalizer()];
  }


  private function getEncoders() : array {
    $format = $this->preferred_supported_format ?: $this->getPreferredSupportedFormat();
    $encoder_class = "\\Symfony\\Component\\Serializer\\Encoder\\" . $this->ucFirstLcRest($format) . "Encoder";
    return [new $encoder_class()];
  }


  private function getPreferredSupportedFormat() : String {
    $preferred_supported_format;

    $accepted_content_types = $this->request->getAcceptableContentTypes();

    foreach($accepted_content_types as $content_type) {
      $format = $this->request->getFormat($content_type);


      if($this->isSupportedFormat($format)) {
        $preferred_supported_format = $format;
        break;
      }
    }

    return $preferred_supported_format ?: $this->getDefaultFormat();
  }


  private function getDefaultFormat() {
    return array_keys($this->supported_formats)[0];
  }


  private function isSupportedFormat($format) {
    return (false !== in_array(strtolower($format), array_keys($this->supported_formats)));
  }


  private function ucFirstLcRest($text) {
    return strtoupper(substr($text, 0, 1)) . strtolower(substr($text, 1));
  }

}

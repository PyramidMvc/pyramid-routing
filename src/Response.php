<?php
/**
 * App          : Pyramid PHP Fremework
 * Author       : Nihat Doğan
 * Email        : info@pyramid.com
 * Website      : https://www.pyramid.com
 * Created Date : 01/01/2025
 * License GPL
 *
 */

namespace Pyramid;


class Response {
	/** Yanıt gövdesini tutan özellik */
	private static $content;
	/** HTTP başlıklarını tutan özellik */
	private static $headers = [];
	/** HTTP durum kodu */
	private static $statusCode = 200;

	public static $view;


	/**
	 * @param $content
	 * @param $statusCode
	 * @param $headers
	 * Response sınıfı için constructor
	 */
	public function __construct( $content = '', $statusCode = 200, $headers = [] ) {
		self::$content    = $content;
		self::$statusCode = $statusCode;
		self::$headers    = $headers;
	}


	/**
	 * @param $name
	 * @param $value
	 * Başlıkları ayarlama
	 *
	 * @return void
	 */
	public static function setHeader( $name, $value ) {
		self::$headers[ $name ] = $value;
	}


	/**
	 * @param $statusCode
	 * Durum kodu ayarlama
	 *
	 * @return void
	 */
	public static function setStatusCode( $statusCode ) {
		self::$statusCode = $statusCode;
	}


	/**
	 * @return void
	 * Yanıtı gönderme
	 */
	public static function send() {
		/** Durum kodunu gönder */
		http_response_code( self::$statusCode );

		/** Başlıkları gönder */
		foreach ( self::$headers as $name => $value ) {
			header( "$name: $value" );
		}

		/** Yanıt içeriğini gönder */
		echo self::$content;
	}


	/**
	 * @param $data
	 * SON yanıtı gönderme
	 *
	 * @return void
	 */
	public static function json( $data ) {
		Response::setHeader( 'Content-Type', 'application/json' );
		Response::setStatusCode( 200 );
		self::$content = json_encode( $data );
		Response::send();
	}


	/**
	 * @param $view
	 * @param $data
	 * Görünüm (view) ile HTML yanıtı gönderme
	 *
	 * @return void
	 */
	public function view( $view, $data = [] ) {

		extract( $data );
		$viewPath = root_path( "/materials/views/{$view}.view.php" );
		ob_start();
		require_once $viewPath;
		self::$view = ob_get_clean();
		TemplateEngine::parse();

		ob_start();
		eval('?>'. self::$view);
		self::$view = ob_get_clean();
		TemplateEngine::parse();


		$viewCachePath = $_SERVER['DOCUMENT_ROOT'] . '/repository/cache/' . md5( "$view.php" ) . '.cache.php';


		if ( ! file_exists( $viewCachePath )) {
			file_put_contents( $viewCachePath, self::$view );
		} elseif ( file_exists( $viewCachePath ) || filemtime( $viewCachePath ) < filemtime( $viewPath ) ) {

			file_put_contents( $viewCachePath, self::$view );
		}

		// Sonuç olarak cache dosyasını bir kez daha kontrol et ve varsa içeriği tekrar yazma
		if ( file_exists( $viewCachePath ) ) {
			require_once $viewCachePath;
			exit();
		}

	}


	/**
	 * @param $url
	 * Yönlendirme (redirect)
	 *
	 * @return void
	 */
	public static function redirect( $url ) {
		Response::setStatusCode( 302 ); /** Yönlendirme için 302 kodu */
		Response::setHeader( 'Location', $url );
		Response::send();
		exit;  // Yönlendirme sonrası işlemi sonlandırıyoruz
	}
}

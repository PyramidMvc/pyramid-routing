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

use Pyramid\Request;
use Pyramid\Core\Kernel;

class Router {
	protected static $routes = [];
	protected static $prefix = '';
	protected static $routeNames = [];
	protected static $middleware = [];

	/**
	 * @param $startUri
	 * @param $endUri
	 * Route redirect url yönlendirme
	 *
	 * @return null
	 */
	public static function redirect( $startUri, $endUri ) {

		return header( 'Location: ' . $endUri );
	}



	/**
	 * @param $uri
	 * @param $controllerAction
	 * Middleware ekleme fonksiyonu
	 *
	 * @return Router
	 */
	public static function get( $uri, $controllerAction ) {
		/** Eğer gelen $controllerAction bir Closure (anonim fonksiyon) ise */
		if ( $controllerAction instanceof \Closure ) {
			/** Closure'ı doğrudan rotaya ekle */
			self::addRoute( 'GET', $uri, $controllerAction);
		} else {
			/** Diğer durumlarda, gelen aksiyonu (controller, action) ekle */
			self::addRoute( 'GET', $uri, $controllerAction );
		}

		return new self();
	}

	public static function post( $uri, $controllerAction ) {
		/** Eğer gelen $controllerAction bir Closure (anonim fonksiyon) ise */
		if ( $controllerAction instanceof \Closure ) {
			/** Closure'ı doğrudan rotaya ekle */
			self::addRoute( 'POST', $uri, $controllerAction);
		} else {
			/** Diğer durumlarda, gelen aksiyonu (controller, action) ekle */
			self::addRoute( 'POST', $uri, $controllerAction );
		}
		return new self();
	}


	/**
	 * @param $name
	 * Rota ismi ekleme
	 *
	 * @return Router
	 */
	public static function name( $name ) {

		$lastRoute = end( self::$routes );
		/** En son eklenen rota */
		self::$routeNames[ $name ] = $lastRoute['uri'];

		/** İsme karşılık gelen URL'yi kaydet */
		return new self();
	}


	/**
	 * @param $name
	 * Route url eşleştirme
	 *
	 * @return string|null
	 */
	public static function route($route,$id='') {

		// Dosyayı oku
		$fileContent = file_get_contents(root_path('/routes/web.php'));
		// Tüm boşluk, yeni satır ve tab karakterlerini kaldır
		$fileContent = preg_replace('/\s+/', '', $fileContent);
		// Regex ile rota adlarını ve URL'leri çıkar
		preg_match_all('/Router::(get|post|put|delete|patch)\([\'"]([^\'"]+)[\'"],.*?->name\([\'"]([^\'"]+)[\'"]\);/', $fileContent, $matches);

		// Rota isimleri ve URL'lerini eşleştir
		foreach ($matches[2] as $index => $routeName) {
			if ($route==$matches[3][$index]){
				$parameter = preg_replace( '/\{(\w+)\}/', $id, $routeName ); // {id} => ([a-zA-Z0-9-_]+)

				return url($id?$parameter:$routeName);
			}
		}
		return null;
	}

	/**
	 * @param $method
	 * @param $uri
	 * @param $controllerAction
	 * Route metodlarını ekliyoruz
	 *
	 * @return void
	 */
	protected static function addRoute( $method, $uri, $controllerAction) {
			$uri            = self::$prefix . $uri;
			self::$routes[] = [ 'method' => $method, 'uri' => $uri, 'action' => $controllerAction ];

	}

	/**
	 * @param $attributes
	 * @param $callback
	 * Route Gruplama
	 *
	 * @return void
	 */
	public static function group( $attributes, $callback ) {
		/** Grup için prefix ve middleware değerlerini ayarla */
		self::$prefix       = $attributes['prefix'] ?? '';
		self::$middleware[] = $attributes['middleware'] ?? [];

		if ( !empty($attributes['middleware']) ) {
			self::$middleware[]='csrf';
			/** Callback'i çalıştır */
			$callback( new self() );

			Router::dispatch();

			self::$prefix     = '';
			self::$middleware = [];

		}else{

			$callback( new self() );
			self::$prefix     = '';
			self::$middleware = [];
			self::$middleware[]='csrf';
			Router::dispatch();

		}



	}

	public static function dispatch() {

		/** URL ve metod bilgilerini al */
		$method = $_SERVER['REQUEST_METHOD'];
		/** GET, POST vb. */
		$uri = strtok( $_SERVER['REQUEST_URI'], '?' );
		/** URL'nin tamamı */

		$request = new Request();

		foreach ( self::$routes as $index => $route ) {
			/** Rotadaki parametreleri yakalamak için regex kullanıyoruz */
			$pattern = preg_replace( '/\{(\w+)\}/', '([a-zA-Z0-9-_]+)', $route['uri'] ); // {id} => ([a-zA-Z0-9-_]+)
			$pattern = "#" . $pattern . "$#";



			if ( preg_match( $pattern, $uri, $matches ) && strpos( $route['uri'], '{' ) !== false ) {
				/** İlk eleman rotanın kendisi, onu çıkarıyoruz */

				array_shift( $matches );
				$params = $matches[0];


				/** Eğer route'da Closure varsa, onu çalıştır */
				if ( $route['action'] instanceof \Closure ) {
					$response = call_user_func( $route['action'], $params );

					/** Response objesi döndü ise, send metodunu çağır */
					if ( $response instanceof Response ) {
						return $response->send();
					} else {
						/** Eğer response bir metin ise, basitçe echo yap */
						echo $response;
					}
				}


				if ( isset( self::$middleware ) && ! empty( self::$middleware ) ) {
					/** Middleware'leri sırayla çalıştır */
					foreach ( self::$middleware as $key => $middlewareAlias ) {

						$middlewareClass = Kernel::getMiddlewareClass( $middlewareAlias );
						if ( $middlewareClass ) {

							Kernel::handle( $middlewareClass, function () use ( $route, $params ) {

								/** Controller'ı çağır */
								$action     = $route['action'];
								$controller = new $action[0]();

								$response = call_user_func( [ $controller, $action[1] ], $params );

								/** Response objesi döndü ise, send metodunu çağır */
								if ( $response instanceof Response ) {
									return $response->send();
								} else {
									/** Eğer response bir metin ise, basitçe echo yap */
									echo $response;
								}


							} );
						}
					}
				}



				/** Eğer route'da Closure varsa, onu çalıştır */
				if ( $route['action'] instanceof \Closure ) {
					$response = call_user_func( $route['action'], $params );

					/** Response objesi döndü ise, send metodunu çağır */
					if ( $response instanceof Response ) {
						return $response->send();
					} else {
						/** Eğer response bir metin ise, basitçe echo yap */
						echo $response;
					}
				}

				/** Eğer parametre yoksa, boş bir array ile çağırıyoruz */
				$response = call_user_func( [ $controller, $action[1] ], $params );

				/** Response objesi döndü ise, send metodunu çağır */
				if ( $response instanceof Response ) {
					return $response->send();
				} else {
					/** Eğer response bir metin ise, basitçe echo yap */
					echo $response;
				}

			}

			/** Parametre yoksa controller'ı çalıştır */
			if ( $route['method'] === $method && rtrim($route['uri'],'/') === rtrim($uri,'/') ) {


				if ( isset( self::$middleware ) && ! empty( self::$middleware ) ) {


					/** Eğer route'da Closure varsa, onu çalıştır */
					if ( $route['action'] instanceof \Closure ) {
						$response = call_user_func( $route['action'], $request );

						/** Response objesi döndü ise, send metodunu çağır */
						if ( $response instanceof Response ) {
							return $response->send();
						} else {
							/** Eğer response bir metin ise, basitçe echo yap */
							echo $response;
						}
					}


					/** Middleware'leri çalıştır */
					foreach ( self::$middleware as $key => $middlewareAlias ) {

						$middlewareClass = Kernel::getMiddlewareClass( $middlewareAlias );

						if ( $middlewareClass ) {

							Kernel::handle( '\\' . $middlewareClass, function () use ( $request, $route ) {

								/** Parametre yoksa, boş bir array ile çağırıyoruz */
								$action     = $route['action'];
								$controller = new $action[0]();

								$response = call_user_func( [ $controller, $action[1] ], $request );

								/** Response objesi döndü ise, send metodunu çağır */
								if ( $response instanceof Response ) {
									$response->send();
								} else {
									/** Eğer response bir metin ise, basitçe echo yap */
									echo $response;
								}

							} );
						}

					}


				}


				$action     = $route['action'];
				$controller = new $action[0]();

				/** Eğer route'da Closure varsa, onu çalıştır */
				if ( $route['action'] instanceof \Closure ) {
					$response = call_user_func( $route['action'], $request );

					/** Response objesi döndü ise, send metodunu çağır */
					if ( $response instanceof Response ) {
						return $response->send();
					} else {
						/** Eğer response bir metin ise, basitçe echo yap */
						echo $response;
					}
				}

				/** Eğer parametre yoksa, boş bir array ile çağırıyoruz */
				$response = call_user_func( [ $controller, $action[1] ], $request );

				/** Response objesi döndü ise, send metodunu çağır */
				if ( $response instanceof Response ) {
					return $response->send();
				} else {
					/** Eğer response bir metin ise, basitçe echo yap */
					echo $response;
				}

			}


		}

		return "Route not found";
	}


	/**
	 * @return string
	 * Perfix metodu fonksiyonu
	 */
	public static function prefix() {
		if ( self::$prefix ) {

			return self::$prefix;
		}
		return '';
	}


}

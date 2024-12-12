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

class Redirect {

	/**
	 * @return void
	 * Kullanıcıyı geri yönlendiren back() fonksiyonu
	 */
	public static function back() {

		/** PHP'de header ile geri yönlendirme yapılır.*/
		header( 'Location: ' . $_SERVER['HTTP_REFERER'] );
		exit();
		/** Yönlendirmeden sonra işlemi sonlandırmak önemli*/
	}


	/**
	 * @param $value
	 * with() fonksiyonu, session veya verileri set eder
	 *
	 * @return array
	 */
	public static function with( $value ){
		/** $key ve $value'yu session'a kaydediyoruz*/
		foreach ( $value as $k => $v ) {
			session( $k, $v );
		}
		/** Zincirleme çağrı için return $this yapıyoruz*/
		return new self();
	}


	/**
	 * @param $name
	 * Rota yönlendirme fonksiyonu
	 *
	 * @return null
	 */
	public static function route( $name ) {
		/** Route url'ye eşitleme işlemi yapıyoruz */
		$route = Router::route( $name );
		header( 'Location: ' . $route );
		exit;
	}

	/**
	 * @param $name
	 * Url yönlendirme fonksiyonu
	 *
	 * @return null
	 */
	public static function url( $name ) {

		return header( 'Location: ' . $name );
	}

}
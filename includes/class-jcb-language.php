<?php
/**
 * Language helpers for front-end copy and answer rules.
 *
 * @package JeroensChatbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JCB_Language {

	/**
	 * Supported plugin languages.
	 */
	public static function languages(): array {
		return array(
			'en' => array(
				'name'    => 'English',
				'native'  => 'English',
				'strings' => array(
					'welcome_message' => 'Hi. How can I help you?',
					'placeholder'     => 'Ask a question...',
					'launcher_label'  => 'Chat',
					'instructions'     => 'Answer questions using the selected website knowledge base. Be clear, helpful and honest. If the answer is not in the knowledge base, say that you do not know based on the available site content.',
					'send'             => 'Send',
					'close'            => 'Close',
					'typing'           => 'Typing...',
					'sources'          => 'Sources',
					'source'           => 'Source',
					'helpful'          => 'Helpful',
					'not_helpful'      => 'Not helpful',
					'feedback_thanks'  => 'Thanks for your feedback.',
					'error_answer'     => 'The chatbox could not answer right now.',
					'no_answer'        => 'No answer returned.',
				),
			),
			'nl' => array(
				'name'    => 'Dutch',
				'native'  => 'Nederlands',
				'strings' => array(
					'welcome_message' => 'Hoi. Waar kan ik je mee helpen?',
					'placeholder'     => 'Stel je vraag...',
					'launcher_label'  => 'Chat',
					'instructions'     => 'Beantwoord vragen met de geselecteerde kennisbank van de website. Wees duidelijk, behulpzaam en eerlijk. Als het antwoord niet in de kennisbank staat, zeg dan dat je het niet weet op basis van de beschikbare site inhoud.',
					'send'             => 'Verstuur',
					'close'            => 'Sluiten',
					'typing'           => 'Aan het typen...',
					'sources'          => 'Bronnen',
					'source'           => 'Bron',
					'helpful'          => 'Nuttig',
					'not_helpful'      => 'Niet nuttig',
					'feedback_thanks'  => 'Bedankt voor je feedback.',
					'error_answer'     => 'De chatbox kan nu geen antwoord geven.',
					'no_answer'        => 'Geen antwoord ontvangen.',
				),
			),
			'de' => array(
				'name'    => 'German',
				'native'  => 'Deutsch',
				'strings' => array(
					'welcome_message' => 'Hallo. Wie kann ich dir helfen?',
					'placeholder'     => 'Stelle eine Frage...',
					'launcher_label'  => 'Chat',
					'instructions'     => 'Beantworte Fragen mit der ausgewählten Wissensdatenbank der Website. Sei klar, hilfreich und ehrlich. Wenn die Antwort nicht in der Wissensdatenbank steht, sage, dass du es anhand der verfügbaren Website Inhalte nicht weißt.',
					'send'             => 'Senden',
					'close'            => 'Schließen',
					'typing'           => 'Tippt...',
					'sources'          => 'Quellen',
					'source'           => 'Quelle',
					'helpful'          => 'Hilfreich',
					'not_helpful'      => 'Nicht hilfreich',
					'feedback_thanks'  => 'Danke für dein Feedback.',
					'error_answer'     => 'Die Chatbox kann gerade nicht antworten.',
					'no_answer'        => 'Keine Antwort erhalten.',
				),
			),
			'fr' => array(
				'name'    => 'French',
				'native'  => 'Français',
				'strings' => array(
					'welcome_message' => 'Bonjour. Comment puis je vous aider ?',
					'placeholder'     => 'Posez une question...',
					'launcher_label'  => 'Chat',
					'instructions'     => 'Répondez aux questions à partir de la base de connaissances sélectionnée du site. Soyez clair, utile et honnête. Si la réponse ne se trouve pas dans la base de connaissances, dites que vous ne savez pas d après le contenu disponible du site.',
					'send'             => 'Envoyer',
					'close'            => 'Fermer',
					'typing'           => 'Rédaction...',
					'sources'          => 'Sources',
					'source'           => 'Source',
					'helpful'          => 'Utile',
					'not_helpful'      => 'Pas utile',
					'feedback_thanks'  => 'Merci pour votre retour.',
					'error_answer'     => 'La chatbox ne peut pas répondre pour le moment.',
					'no_answer'        => 'Aucune réponse reçue.',
				),
			),
		);
	}

	/**
	 * Supported language codes.
	 */
	public static function codes(): array {
		return array_keys( self::languages() );
	}

	/**
	 * Normalize language code.
	 *
	 * @param string $language Language code.
	 */
	public static function normalize( string $language ): string {
		$language = strtolower( sanitize_key( $language ) );
		return in_array( $language, self::codes(), true ) ? $language : 'en';
	}

	/**
	 * Return language options for the admin app.
	 */
	public static function admin_options(): array {
		$options = array();
		foreach ( self::languages() as $code => $language ) {
			$options[] = array(
				'code'   => $code,
				'name'   => $language['name'],
				'native' => $language['native'],
			);
		}
		return $options;
	}

	/**
	 * Get a language string.
	 *
	 * @param string $key String key.
	 * @param string $language Language code.
	 */
	public static function text( string $key, string $language = 'en' ): string {
		$language  = self::normalize( $language );
		$languages = self::languages();
		$strings   = $languages[ $language ]['strings'] ?? array();
		$fallback  = $languages['en']['strings'] ?? array();
		return (string) ( $strings[ $key ] ?? $fallback[ $key ] ?? '' );
	}

	/**
	 * Get all front-end JavaScript strings.
	 *
	 * @param string $language Language code.
	 */
	public static function front_end_strings( string $language = 'en' ): array {
		$language = self::normalize( $language );
		return array(
			'send'           => self::text( 'send', $language ),
			'close'          => self::text( 'close', $language ),
			'typing'         => self::text( 'typing', $language ),
			'sources'        => self::text( 'sources', $language ),
			'source'         => self::text( 'source', $language ),
			'helpful'        => self::text( 'helpful', $language ),
			'notHelpful'     => self::text( 'not_helpful', $language ),
			'feedbackThanks' => self::text( 'feedback_thanks', $language ),
			'errorAnswer'    => self::text( 'error_answer', $language ),
			'noAnswer'       => self::text( 'no_answer', $language ),
		);
	}

	/**
	 * Return all default text values for a setting key.
	 *
	 * @param string $key String key.
	 */
	public static function default_candidates( string $key ): array {
		$values = array();
		foreach ( self::codes() as $code ) {
			$value = self::text( $key, $code );
			if ( '' !== $value ) {
				$values[] = $value;
			}
		}
		return array_values( array_unique( $values ) );
	}

	/**
	 * Return answer language instruction.
	 *
	 * @param string $language Language code.
	 */
	public static function response_rule( string $language = 'en' ): string {
		$language = self::normalize( $language );
		switch ( $language ) {
			case 'nl':
				return 'Answer visitors in Dutch unless they clearly ask for another language.';
			case 'de':
				return 'Answer visitors in German unless they clearly ask for another language.';
			case 'fr':
				return 'Answer visitors in French unless they clearly ask for another language.';
			case 'en':
			default:
				return 'Answer visitors in English unless they clearly ask for another language.';
		}
	}
}

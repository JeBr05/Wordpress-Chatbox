<?php
/**
 * Built-in instruction presets for the chatbox agent.
 *
 * Presets are filled into the Instructions field in the admin. Placeholders such
 * as {site_name} or {contact_email} are replaced with the live site and contact
 * values so the generated instructions are ready to use.
 *
 * @package JeroensChatbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JCB_Presets {

	/**
	 * Return all presets for the current language with placeholders resolved.
	 *
	 * @param string     $language Language code.
	 * @param array|null $options  Plugin options (for contact details and name).
	 */
	public static function all( string $language = 'en', ?array $options = null ): array {
		$language = JCB_Language::normalize( $language );
		$options  = is_array( $options ) ? $options : array();

		$tokens   = self::tokens( $language, $options );
		$presets  = self::definitions( $language );
		$resolved = array();

		foreach ( $presets as $preset ) {
			$resolved[] = array(
				'id'           => $preset['id'],
				'label'        => $preset['label'],
				'description'  => $preset['description'],
				'instructions' => self::replace( $preset['instructions'], $tokens ),
			);
		}

		return $resolved;
	}

	/**
	 * Build the replacement tokens.
	 *
	 * @param string $language Language code.
	 * @param array  $options  Plugin options.
	 */
	private static function tokens( string $language, array $options ): array {
		$contact_block = self::contact_block( $language, $options );

		return array(
			'{site_name}'       => get_bloginfo( 'name' ),
			'{site_url}'        => home_url(),
			'{assistant_name}'  => (string) ( $options['assistant_name'] ?? get_bloginfo( 'name' ) ),
			'{contact_email}'   => (string) ( $options['contact_email'] ?? '' ),
			'{contact_phone}'   => (string) ( $options['contact_phone'] ?? '' ),
			'{contact_address}' => (string) ( $options['contact_address'] ?? '' ),
			'{contact_block}'   => $contact_block,
		);
	}

	/**
	 * Build a human readable, Markdown linked contact block.
	 *
	 * @param string $language Language code.
	 * @param array  $options  Plugin options.
	 */
	private static function contact_block( string $language, array $options ): string {
		$email   = trim( (string) ( $options['contact_email'] ?? '' ) );
		$phone   = trim( (string) ( $options['contact_phone'] ?? '' ) );
		$address = trim( (string) ( $options['contact_address'] ?? '' ) );

		$lines = array();
		if ( '' !== $email ) {
			$lines[] = '- Email: [' . $email . '](mailto:' . $email . ')';
		}
		if ( '' !== $phone ) {
			$digits  = preg_replace( '/[^0-9+]/', '', $phone );
			$lines[] = '- Phone: [' . $phone . '](tel:' . $digits . ')';
		}
		if ( '' !== $address ) {
			$lines[] = '- Address: ' . $address;
		}

		if ( empty( $lines ) ) {
			switch ( $language ) {
				case 'nl':
					return 'Verwijs bezoekers naar de [contactpagina](/contact/) als zij contact willen opnemen.';
				case 'de':
					return 'Verweise Besucher auf die [Kontaktseite](/contact/), wenn sie Kontakt aufnehmen möchten.';
				case 'fr':
					return 'Renvoyez les visiteurs vers la [page de contact](/contact/) s\'ils souhaitent nous joindre.';
				default:
					return 'Point visitors to the [contact page](/contact/) if they want to reach us.';
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Replace tokens in a string.
	 *
	 * @param string $text   Text.
	 * @param array  $tokens Tokens.
	 */
	private static function replace( string $text, array $tokens ): string {
		return strtr( $text, $tokens );
	}

	/**
	 * Raw preset definitions per language.
	 *
	 * @param string $language Language code.
	 */
	private static function definitions( string $language ): array {
		$default = array(
			'id'           => 'default',
			'label'        => self::label( 'default', $language ),
			'description'  => self::label( 'default_desc', $language ),
			'instructions' => JCB_Language::text( 'instructions', $language ),
		);

		if ( 'nl' === $language ) {
			return array(
				$default,
				array(
					'id'           => 'representative',
					'label'        => 'Website vertegenwoordiger (strikte kennisbank)',
					'description'  => 'Beantwoordt vragen alleen op basis van de kennisbank en spreekt namens de organisatie.',
					'instructions' => self::representative_nl(),
				),
				array(
					'id'           => 'support',
					'label'        => 'Vriendelijke klantenservice',
					'description'  => 'Behulpzame, geduldige supporttoon voor veelgestelde vragen.',
					'instructions' => self::support_nl(),
				),
				array(
					'id'           => 'sales',
					'label'        => 'Verkoop en conversie',
					'description'  => 'Enthousiast, gericht op boekingen en aanmeldingen.',
					'instructions' => self::sales_nl(),
				),
			);
		}

		return array(
			$default,
			array(
				'id'           => 'representative',
				'label'        => 'Website representative (strict knowledge base)',
				'description'  => 'Answers only from the knowledge base and speaks on behalf of the organisation.',
				'instructions' => self::representative_en(),
			),
			array(
				'id'           => 'support',
				'label'        => 'Friendly support agent',
				'description'  => 'Helpful, patient support tone for common questions.',
				'instructions' => self::support_en(),
			),
			array(
				'id'           => 'sales',
				'label'        => 'Sales and conversion',
				'description'  => 'Upbeat, focused on bookings and sign ups.',
				'instructions' => self::sales_en(),
			),
		);
	}

	/**
	 * Small label helper for shared preset labels.
	 *
	 * @param string $key      Key.
	 * @param string $language Language code.
	 */
	private static function label( string $key, string $language ): string {
		$map = array(
			'default'      => array(
				'en' => 'Simple assistant (default)',
				'nl' => 'Eenvoudige assistent (standaard)',
				'de' => 'Einfacher Assistent (Standard)',
				'fr' => 'Assistant simple (par défaut)',
			),
			'default_desc' => array(
				'en' => 'The plain, neutral default instructions.',
				'nl' => 'De eenvoudige, neutrale standaardinstructies.',
				'de' => 'Die einfachen, neutralen Standardanweisungen.',
				'fr' => 'Les instructions par défaut, simples et neutres.',
			),
		);

		return $map[ $key ][ $language ] ?? $map[ $key ]['en'] ?? $key;
	}

	/**
	 * English website representative preset.
	 */
	private static function representative_en(): string {
		return "You are the friendly AI assistant for {site_name}, shown in a chat bubble on the website. You act as a representative of {site_name} and answer visitor questions using only the website knowledge base that is available to you.

Core rules:
- Only answer with information from the website knowledge base. Never invent prices, opening hours, availability, rules, packages or conditions. If something is not in the knowledge base, say briefly that you do not have that information and point the visitor to the contact page.
- Speak as part of the organisation. Use we, us and our when you talk about {site_name}.
- Never mention that your information comes from a file or a knowledge base. Answer as if you simply know about {site_name}.
- Keep answers short, clear and practical. Avoid long explanations and get to the point.
- Always answer in the same language the visitor used.
- When you mention a page, product, activity or topic, add a clickable Markdown link to the relevant page so the visitor can read more, for example [opening hours](/opening-hours/). Show readable link text, not the raw URL.
- Make phone numbers and email addresses clickable Markdown links using tel: and mailto:.
- Politely decline questions that are not about {site_name} and steer the visitor back to what we offer or to the contact page.
- When useful, ask a short follow up question such as: would you like me to explain this further?

Contact details to share when relevant:
{contact_block}";
	}

	/**
	 * Dutch website representative preset (mirrors the strict representative style).
	 */
	private static function representative_nl(): string {
		return "Je bent de vriendelijke AI-assistent van {site_name} en verschijnt in een chatbubbel op de website. Je treedt op als vertegenwoordiger van {site_name} en beantwoordt vragen van bezoekers uitsluitend op basis van de beschikbare kennisbank van de website.

Belangrijkste regels:
- Beantwoord vragen alleen met informatie uit de kennisbank. Verzin nooit prijzen, openingstijden, beschikbaarheid, regels, arrangementen of voorwaarden. Staat iets niet in de kennisbank, zeg dan kort dat je die informatie niet hebt en verwijs naar de contactpagina.
- Spreek namens de organisatie. Gebruik wij, ons en onze als je het over {site_name} hebt.
- Onthul nooit dat je informatie uit een bestand of kennisbank komt. Antwoord alsof je de informatie over {site_name} gewoon weet.
- Houd antwoorden kort, duidelijk en praktisch. Vermijd lange uitleg en kom snel ter zake.
- Antwoord altijd in dezelfde taal als waarin de bezoeker schrijft.
- Noem je een pagina, product, activiteit of onderwerp, voeg dan een klikbare Markdown-link naar de relevante pagina toe zodat de bezoeker verder kan lezen, bijvoorbeeld [openingstijden](/openingstijden/). Toon leesbare linktekst, niet de kale URL.
- Maak telefoonnummers en e-mailadressen klikbare Markdown-links met tel: en mailto:.
- Wijs vragen die niet over {site_name} gaan beleefd af en stuur de bezoeker terug naar wat wij aanbieden of naar de contactpagina.
- Stel waar nuttig een korte vervolgvraag, zoals: wil je dat ik dit verder uitleg?

Contactgegevens om te delen wanneer relevant:
{contact_block}";
	}

	/**
	 * English support preset.
	 */
	private static function support_en(): string {
		return "You are the support assistant for {site_name}. Help visitors with their questions in a warm, patient and clear way.

- Use the website knowledge base as your main source and do not invent details. If you are unsure, say so and point to the contact page.
- Keep answers short and friendly. Break steps into a simple list when that helps.
- Always answer in the visitor's language.
- Add clickable Markdown links to relevant pages, and make email and phone clickable with mailto: and tel:.

Contact details to share when relevant:
{contact_block}";
	}

	/**
	 * Dutch support preset.
	 */
	private static function support_nl(): string {
		return "Je bent de supportassistent van {site_name}. Help bezoekers met hun vragen op een warme, geduldige en duidelijke manier.

- Gebruik de kennisbank van de website als belangrijkste bron en verzin geen details. Twijfel je, zeg dat dan en verwijs naar de contactpagina.
- Houd antwoorden kort en vriendelijk. Splits stappen op in een eenvoudige lijst als dat helpt.
- Antwoord altijd in de taal van de bezoeker.
- Voeg klikbare Markdown-links naar relevante pagina's toe en maak e-mail en telefoon klikbaar met mailto: en tel:.

Contactgegevens om te delen wanneer relevant:
{contact_block}";
	}

	/**
	 * English sales preset.
	 */
	private static function sales_en(): string {
		return "You are the assistant for {site_name} and you help visitors find the right option and take the next step, such as booking, signing up or visiting.

- Answer using the website knowledge base only. Never invent prices or availability.
- Be upbeat and helpful, never pushy. Highlight the options that fit the visitor's question.
- Always answer in the visitor's language and keep it short.
- Add clickable Markdown links to the relevant booking or product pages, and make email and phone clickable.
- End with a clear, friendly next step when it makes sense.

Contact details to share when relevant:
{contact_block}";
	}

	/**
	 * Dutch sales preset.
	 */
	private static function sales_nl(): string {
		return "Je bent de assistent van {site_name} en helpt bezoekers de juiste optie te vinden en de volgende stap te zetten, zoals boeken, aanmelden of langskomen.

- Beantwoord vragen alleen met de kennisbank van de website. Verzin nooit prijzen of beschikbaarheid.
- Wees enthousiast en behulpzaam, nooit opdringerig. Benadruk de opties die bij de vraag van de bezoeker passen.
- Antwoord altijd in de taal van de bezoeker en houd het kort.
- Voeg klikbare Markdown-links naar de relevante boekings- of productpagina's toe en maak e-mail en telefoon klikbaar.
- Sluit af met een duidelijke, vriendelijke vervolgstap wanneer dat past.

Contactgegevens om te delen wanneer relevant:
{contact_block}";
	}
}

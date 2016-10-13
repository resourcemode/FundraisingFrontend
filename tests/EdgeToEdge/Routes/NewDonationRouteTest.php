<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Tests\EdgeToEdge\Routes;

use WMDE\Fundraising\Frontend\Tests\EdgeToEdge\WebRouteTestCase;

/**
 * @licence GNU GPL v2+
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class NewDonationRouteTest extends WebRouteTestCase {

	/** @dataProvider paymentInputProvider */
	public function testGivenPaymentInput_paymentDataIsInitiallyValidated( $validPaymentInput, $expectedValidity ) {
		$client = $this->createClient();
		$client->request(
			'POST',
			'/donation/new',
			$validPaymentInput
		);

		$this->assertContains(
			'Payment data: ' . $expectedValidity,
			$client->getResponse()->getContent()
		);
	}

	public function paymentInputProvider() {
		return [
			[
				[
					'betrag_auswahl' => '100',
					'zahlweise' => 'BEZ',
					'periode' => '0'
				],
				'valid'
			],
			[
				[
					'amountGiven' => '123.45',
					'zahlweise' => 'PPL',
					'periode' => 6
				],
				'valid'
			],
			[
				[
					'betrag_auswahl' => '0',
					'zahlweise' => 'PPL',
					'periode' => 6
				],
				'invalid'
			],
			[
				[
					'betrag_auswahl' => '100',
					'zahlweise' => 'BTC',
					'periode' => 6
				],
				'invalid'
			]
		];
	}

	public function testNoLocaleParameterGiven_amountIsParsedUsingDefaultLocale() {
		$client = $this->createClient();
		$client->request(
			'POST',
			'/donation/new',
			[
				'amountGiven' => '123.45',
				'zahlweise' => 'BEZ',
				'periode' => 0
			]
		);

		$this->assertContains( 'Payment data: valid', $client->getResponse()->getContent() );
		$this->assertContains( 'Amount: 123,45', $client->getResponse()->getContent() );
	}

	public function testGivenLocaleParameter_amountIsParsedUsingGivenLocale() {
		$client = $this->createClient();
		$client->request(
			'POST',
			'/donation/new',
			[
				'amountGiven' => '123,45',
				'locale' => 'de_DE',
				'zahlweise' => 'BEZ',
				'periode' => 0
			]
		);

		$this->assertContains( 'Payment data: valid', $client->getResponse()->getContent() );
		$this->assertContains( 'Amount: 123,45', $client->getResponse()->getContent() );
	}

}

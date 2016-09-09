<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Tests\Integration\DonatingContext\DataAccess;

use Doctrine\ORM\EntityManager;
use WMDE\Fundraising\Entities\Donation as DoctrineDonation;
use WMDE\Fundraising\Frontend\DonatingContext\DataAccess\DoctrineDonationRepository;
use WMDE\Fundraising\Frontend\DonatingContext\Domain\Model\Donation;
use WMDE\Fundraising\Frontend\DonatingContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\Frontend\DonatingContext\Domain\Repositories\StoreDonationException;
use WMDE\Fundraising\Frontend\Tests\Data\ValidDoctrineDonation;
use WMDE\Fundraising\Frontend\Tests\Data\ValidDonation;
use WMDE\Fundraising\Frontend\Tests\Fixtures\ThrowingEntityManager;
use WMDE\Fundraising\Frontend\Tests\TestEnvironment;

/**
 * @covers WMDE\Fundraising\Frontend\DonatingContext\DataAccess\DoctrineDonationRepository
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DoctrineDonationRepositoryTest extends \PHPUnit_Framework_TestCase {

	const ID_OF_DONATION_NOT_IN_DB = 35505;

	/**
	 * @var EntityManager
	 */
	private $entityManager;

	public function setUp() {
		$factory = TestEnvironment::newInstance()->getFactory();
		$factory->disableDoctrineSubscribers();
		$this->entityManager = $factory->getEntityManager();
		parent::setUp();
	}

	public function testValidDonationGetPersisted() {
		$donation = ValidDonation::newDirectDebitDonation();

		$this->newRepository()->storeDonation( $donation );

		$expectedDoctrineEntity = ValidDoctrineDonation::newDirectDebitDoctrineDonation();
		$expectedDoctrineEntity->setId( $donation->getId() );

		$this->assertDoctrineEntityIsInDatabase( $expectedDoctrineEntity );
	}

	private function newRepository(): \WMDE\Fundraising\Frontend\DonatingContext\DataAccess\DoctrineDonationRepository {
		return new DoctrineDonationRepository( $this->entityManager );
	}

	private function assertDoctrineEntityIsInDatabase( DoctrineDonation $expected ) {
		$actual = $this->getDoctrineDonationById( $expected->getId() );

		$this->assertNotNull( $actual->getCreationTime() );
		$actual->setCreationTime( null );

		$this->assertEquals( $expected->getDecodedData(), $actual->getDecodedData() );
		// TODO: is this still necessary?
		$expected->encodeAndSetData( [] );
		$actual->encodeAndSetData( [] );

		$this->assertEquals( $expected, $actual );
	}

	private function getDoctrineDonationById( int $id ): DoctrineDonation {
		$donationRepo = $this->entityManager->getRepository( DoctrineDonation::class );
		$donation = $donationRepo->find( $id );
		$this->assertInstanceOf( DoctrineDonation::class, $donation );
		return $donation;
	}

	public function testWhenPersistenceFails_domainExceptionIsThrown() {
		$donation = ValidDonation::newDirectDebitDonation();

		$repository = new DoctrineDonationRepository( ThrowingEntityManager::newInstance( $this ) );

		$this->expectException( StoreDonationException::class );
		$repository->storeDonation( $donation );
	}

	public function testNewDonationPersistenceRoundTrip() {
		$donation = ValidDonation::newDirectDebitDonation();

		$repository = $this->newRepository();

		$repository->storeDonation( $donation );

		$this->assertEquals(
			$donation,
			$repository->getDonationById( $donation->getId() )
		);
	}

	public function testWhenDonationAlreadyExists_persistingCausesUpdate() {
		$repository = $this->newRepository();

		$donation = ValidDonation::newDirectDebitDonation();
		$repository->storeDonation( $donation );

		// It is important a new instance is created here to test "detached entity" handling
		$newDonation = ValidDonation::newDirectDebitDonation();
		$newDonation->assignId( $donation->getId() );
		$newDonation->cancel();
		$repository->storeDonation( $newDonation );

		$this->assertEquals( $newDonation, $repository->getDonationById( $newDonation->getId() ) );
	}

	public function testWhenDonationDoesNotExist_getDonationReturnsNull() {
		$repository = $this->newRepository();

		$this->assertNull( $repository->getDonationById( self::ID_OF_DONATION_NOT_IN_DB ) );
	}

	public function testWhenDoctrineThrowsException_domainExceptionIsThrown() {
		$repository = new DoctrineDonationRepository( ThrowingEntityManager::newInstance( $this ) );

		$this->expectException( GetDonationException::class );
		$repository->getDonationById( self::ID_OF_DONATION_NOT_IN_DB );
	}

	public function testWhenDonationDoesNotExist_persistingCausesException() {
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->assignId( self::ID_OF_DONATION_NOT_IN_DB );

		$repository = $this->newRepository();

		$this->expectException( StoreDonationException::class );
		$repository->storeDonation( $donation );
	}

	public function testWhenDeletionDateGetsSet_repositoryNoLongerReturnsEntity() {
		$donation = $this->createDeletedDonation();
		$repository = $this->newRepository();

		$this->assertNull( $repository->getDonationById( $donation->getId() ) );
	}

	private function createDeletedDonation(): Donation {
		$donation = ValidDonation::newDirectDebitDonation();
		$repository = $this->newRepository();
		$repository->storeDonation( $donation );
		$doctrineDonation = $repository->getDoctrineDonationById( $donation->getId() );
		$doctrineDonation->setDeletionTime( new \DateTime() );
		$this->entityManager->flush();
		return $donation;
	}

	public function testWhenDeletionDateGetsSet_repositoryNoLongerPersistsEntity() {
		$donation = $this->createDeletedDonation();
		$repository = $this->newRepository();

		$this->expectException( StoreDonationException::class );
		$repository->storeDonation( $donation );
	}

	public function testDataFieldsAreRetainedOrUpdatedOnUpdate() {
		$doctrineDonation = $this->getNewlyCreatedDoctrineDonation();

		$doctrineDonation->encodeAndSetData( array_merge(
			$doctrineDonation->getDecodedData(),
			[
				'untouched' => 'value',
				'vorname' => 'potato',
				'another' => 'untouched',
			]
		) );

		$this->entityManager->flush();

		$donation = ValidDonation::newDirectDebitDonation();
		$donation->assignId( $doctrineDonation->getId() );

		$this->newRepository()->storeDonation( $donation );

		$data = $this->getDoctrineDonationById( $donation->getId() )->getDecodedData();

		$this->assertSame( 'value', $data['untouched'] );
		$this->assertNotSame( 'potato', $data['vorname'] );
		$this->assertSame( 'untouched', $data['another'] );
	}

	private function getNewlyCreatedDoctrineDonation(): DoctrineDonation {
		$donation = ValidDonation::newDirectDebitDonation();
		$this->newRepository()->storeDonation( $donation );
		return $this->getDoctrineDonationById( $donation->getId() );
	}

	public function testCommentGetPersistedAndRetrieved() {
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->addComment( ValidDonation::newPublicComment() );

		$repository = $this->newRepository();
		$repository->storeDonation( $donation );

		$retrievedDonation = $repository->getDonationById( $donation->getId() );

		$this->assertEquals( $donation, $retrievedDonation );
	}

	public function testPersistingDonationWithoutCommentCausesCommentToBeCleared() {
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->addComment( ValidDonation::newPublicComment() );

		$repository = $this->newRepository();
		$repository->storeDonation( $donation );

		$newDonation = ValidDonation::newDirectDebitDonation();
		$newDonation->assignId( $donation->getId() );

		$repository->storeDonation( $newDonation );

		$expectedDoctrineEntity = ValidDoctrineDonation::newDirectDebitDoctrineDonation();
		$expectedDoctrineEntity->setId( $donation->getId() );
		$expectedDoctrineEntity->setComment( '' );
		$expectedDoctrineEntity->setIsPublic( false );
		$expectedDoctrineEntity->setPublicRecord( '' );

		$this->assertDoctrineEntityIsInDatabase( $expectedDoctrineEntity );
	}

}
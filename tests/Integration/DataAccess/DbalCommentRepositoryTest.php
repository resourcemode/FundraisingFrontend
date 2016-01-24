<?php

declare(strict_types = 1);

namespace WMDE\Fundraising\Frontend\Tests\Integration\DataAccess;

use DateTime;
use Doctrine\ORM\EntityManager;
use WMDE\Fundraising\Entities\Spenden;
use WMDE\Fundraising\Frontend\DataAccess\DbalCommentRepository;
use WMDE\Fundraising\Frontend\Domain\Comment;
use WMDE\Fundraising\Frontend\Tests\TestEnvironment;

/**
 * @covers WMDE\Fundraising\Frontend\DataAccess\DbalCommentRepository
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DbalCommentRepositoryTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @var EntityManager
	 */
	private $entityManager;

	public function setUp() {
		$this->entityManager = TestEnvironment::newInstance()->getFactory()->getEntityManager();
		parent::setUp();
	}

	private function getOrmRepository() {
		return $this->entityManager->getRepository( Spenden::class );
	}

	public function testWhenThereAreNoComments_anEmptyListIsReturned() {
		$repository = new DbalCommentRepository( $this->getOrmRepository() );

		$this->assertEmpty( $repository->getPublicComments( 10 ) );
	}

	public function testWhenThereAreLessCommentsThanTheLimit_theyAreAllReturned() {
		$this->persistFirstComment();
		$this->persistSecondComment();
		$this->persistThirdComment();
		$this->entityManager->flush();

		$repository = new DbalCommentRepository( $this->getOrmRepository() );

		$this->assertEquals(
			[
				$this->getThirdComment( 3 ),
				$this->getSecondComment(),
				$this->getFirstComment(),
			],
			$repository->getPublicComments( 10 )
		);
	}

	public function testWhenThereAreMoreCommentsThanTheLimit_aLimitedNumberAreReturned() {
		$this->persistFirstComment();
		$this->persistSecondComment();
		$this->persistThirdComment();
		$this->entityManager->flush();

		$repository = new DbalCommentRepository( $this->getOrmRepository() );

		$this->assertEquals(
			[
				$this->getThirdComment( 3 ),
				$this->getSecondComment(),
			],
			$repository->getPublicComments( 2 )
		);
	}

	public function testOnlyPublicCommentsGetReturned() {
		$this->persistFirstComment();
		$this->persistSecondComment();
		$this->persistPrivateComment();
		$this->persistThirdComment();
		$this->entityManager->flush();

		$repository = new DbalCommentRepository( $this->getOrmRepository() );

		$this->assertEquals(
			[
				$this->getThirdComment( 4 ),
				$this->getSecondComment(),
				$this->getFirstComment(),
			],
			$repository->getPublicComments( 10 )
		);
	}

	public function testOnlyNonDeletedCommentsGetReturned() {
		$this->persistFirstComment();
		$this->persistSecondComment();
		$this->persistDeletedComment();
		$this->persistThirdComment();
		$this->entityManager->flush();

		$repository = new DbalCommentRepository( $this->getOrmRepository() );

		$this->assertEquals(
			[
				$this->getThirdComment( 4 ),
				$this->getSecondComment(),
				$this->getFirstComment(),
			],
			$repository->getPublicComments( 10 )
		);
	}

	private function persistFirstComment() {
		$firstSpenden = new Spenden();
		$firstSpenden->setName( 'First name' );
		$firstSpenden->setKommentar( 'First comment' );
		$firstSpenden->setBetrag( '100' );
		$firstSpenden->setDtNew( new DateTime( '1984-01-01' ) );
		$firstSpenden->setIsPublic( true );
		$this->entityManager->persist( $firstSpenden );
	}

	private function persistSecondComment() {
		$secondSpenden = new Spenden();
		$secondSpenden->setName( 'Second name' );
		$secondSpenden->setKommentar( 'Second comment' );
		$secondSpenden->setBetrag( '200' );
		$secondSpenden->setDtNew( new DateTime( '1984-02-02' ) );
		$secondSpenden->setIsPublic( true );
		$this->entityManager->persist( $secondSpenden );
	}

	private function persistThirdComment() {
		$thirdSpenden = new Spenden();
		$thirdSpenden->setName( 'Third name' );
		$thirdSpenden->setKommentar( 'Third comment' );
		$thirdSpenden->setBetrag( '300' );
		$thirdSpenden->setDtNew( new DateTime( '1984-03-03' ) );
		$thirdSpenden->setIsPublic( true );
		$this->entityManager->persist( $thirdSpenden );
	}

	private function persistPrivateComment() {
		$privateSpenden = new Spenden();
		$privateSpenden->setName( 'Private name' );
		$privateSpenden->setKommentar( 'Private comment' );
		$privateSpenden->setBetrag( '1337' );
		$privateSpenden->setDtNew( new DateTime( '1984-12-12' ) );
		$privateSpenden->setIsPublic( false );
		$this->entityManager->persist( $privateSpenden );
	}

	private function persistDeletedComment() {
		$deletedSpenden = new Spenden();
		$deletedSpenden->setName( 'Deleted name' );
		$deletedSpenden->setKommentar( 'Deleted comment' );
		$deletedSpenden->setBetrag( '31337' );
		$deletedSpenden->setDtNew( new DateTime( '1984-11-11' ) );
		$deletedSpenden->setIsPublic( true );
		$deletedSpenden->setDtDel( new DateTime( '2000-01-01' ) );
		$this->entityManager->persist( $deletedSpenden );
	}

	private function getFirstComment() {
		return Comment::newInstance()
			->setAuthorName( 'First name' )
			->setCommentText( 'First comment' )
			->setDonationAmount( 100 )
			->setPostingTime( new \DateTime( '1984-01-01' ) )
			->setDonationId( 1 )
			->freeze();
	}


	private function getSecondComment() {
		return Comment::newInstance()
			->setAuthorName( 'Second name' )
			->setCommentText( 'Second comment' )
			->setDonationAmount( 200 )
			->setPostingTime( new \DateTime( '1984-02-02' ) )
			->setDonationId( 2 )
			->freeze();
	}

	private function getThirdComment( int $donationId ) {
		return Comment::newInstance()
			->setAuthorName( 'Third name' )
			->setCommentText( 'Third comment' )
			->setDonationAmount( 300 )
			->setPostingTime( new \DateTime( '1984-03-03' ) )
			->setDonationId( $donationId )
			->freeze();
	}


}

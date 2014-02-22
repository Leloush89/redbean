<?php 
 namespace RedUNIT\Mysql;
use RedUNIT\Mysql as Mysql;
use RedBeanPHP\Facade as R;

/**
 * RedUNIT_Mysql_Foreignkeys
 *
 * @file    RedUNIT/Mysql/Foreignkeys.php
 * @desc    Tests creation of foreign keys.
 * @author  Gabor de Mooij and the RedBeanPHP Community
 * @license New BSD/GPLv2
 *
 * (c) G.J.G.T. (Gabor) de Mooij and the RedBeanPHP Community.
 * This source file is subject to the New BSD/GPLv2 License that is bundled
 * with this source code in the file license.txt.
 */
class Foreignkeys extends Mysql
{
	/**
	 * Basic FK tests.
	 * 
	 * @return void
	 */
	public function testFKS()
	{
		$book  = R::dispense( 'book' );
		$page  = R::dispense( 'page' );
		$cover = R::dispense( 'cover' );

		list( $g1, $g2 ) = R::dispense( 'genre', 2 );

		$g1->name = '1';
		$g2->name = '2';

		$book->ownPage = array( $page );

		$book->cover = $cover;

		$book->sharedGenre = array( $g1, $g2 );

		R::store( $book );

		$fkbook  = R::getAll( 'describe book' );
		$fkgenre = R::getAll( 'describe book_genre' );
		$fkpage  = R::getAll( 'describe cover' );

		$j = json_encode( R::getAll( 'SELECT
		ke.referenced_table_name parent,
		ke.table_name child,
		ke.constraint_name
		FROM
		information_schema.KEY_COLUMN_USAGE ke
		WHERE
		ke.referenced_table_name IS NOT NULL
		AND ke.CONSTRAINT_SCHEMA="oodb"
		ORDER BY
		constraint_name;' ) );

		$json = '[
			{
				"parent": "genre",
				"child": "book_genre",
				"constraint_name": "book_genre_ibfk_1"
			},
			{
				"parent": "book",
				"child": "book_genre",
				"constraint_name": "book_genre_ibfk_2"
			},
			{
				"parent": "cover",
				"child": "book",
				"constraint_name": "c_fk_book_cover_id"
			},
			{
				"parent": "book",
				"child": "page",
				"constraint_name": "c_fk_page_book_id"
			}
		]';

		$j1 = json_decode( $j, TRUE );
		
		$j2 = json_decode( $json, TRUE );

		foreach ( $j1 as $jrow ) {
			$s = json_encode( $jrow );

			$found = 0;

			foreach ( $j2 as $k => $j2row ) {
				if ( json_encode( $j2row ) === $s ) {
					pass();

					unset( $j2[$k] );

					$found = 1;
				}
			}

			if ( !$found ) fail();
		}
	}

	/**
	 * Test widen for constraint.
	 * 
	 * @return void
	 */
	public function testWideningColumnForConstraint()
	{
		testpack( 'widening column for constraint' );

		$bean1 = R::dispense( 'project' );
		$bean2 = R::dispense( 'invoice' );

		$bean3 = R::getRedBean()->dispense( 'invoice_project' );

		$bean3->project_id = false;
		$bean3->invoice_id = true;

		R::store( $bean3 );

		$cols = R::getColumns( 'invoice_project' );
		
		asrt( $cols['project_id'], "tinyint(1) unsigned" );
		asrt( $cols['invoice_id'], "tinyint(1) unsigned" );

		R::getWriter()->addConstraintForTypes( $bean1->getMeta( 'type' ), $bean2->getMeta( 'type' ) );

		$cols = R::getColumns( 'invoice_project' );

		asrt( $cols['project_id'], "int(11) unsigned" );
		asrt( $cols['invoice_id'], "int(11) unsigned" );
	}
	
	/**
	 * Test adding of constraints directly by invoking
	 * the writer method.
	 * 
	 * @return void
	 */
	public function test_Contrain()
	{
		R::nuke();
		
		$sql   = '
			CREATE TABLE book (
				id INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT, 
				PRIMARY KEY ( id )
			) 
			ENGINE = InnoDB
		';
		
		R::exec( $sql );
		
		$sql   = '
			CREATE TABLE page (
				id INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT, 
				PRIMARY KEY ( id )
			) 
			ENGINE = InnoDB
		';

		R::exec( $sql );
		
		$sql   = '
			CREATE TABLE book_page (
				id INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT,
				book_id INT( 11 ) UNSIGNED NOT NULL,
				page_id INT( 11 ) UNSIGNED NOT NULL,
				PRIMARY KEY ( id )
			) 
			ENGINE = InnoDB
		';

		R::exec( $sql );
		
		$numOfFKS = R::getCell('
			SELECT COUNT(*) 
			FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
			WHERE TABLE_NAME = "book_page" AND DELETE_RULE = "CASCADE"');
		
		asrt( (int) $numOfFKS, 0 );
		
		$writer = R::getWriter();
		
		$writer->addConstraintForTypes( 'book', 'page' );
		
		
		$numOfFKS = R::getCell('
			SELECT COUNT(*) 
			FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
			WHERE TABLE_NAME = "book_page" AND DELETE_RULE = "CASCADE"');
		
		asrt( (int) $numOfFKS, 2 );
		
		$writer->addConstraintForTypes( 'book', 'page' );
		
		
		$numOfFKS = R::getCell('
			SELECT COUNT(*) 
			FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
			WHERE TABLE_NAME = "book_page" AND DELETE_RULE = "CASCADE"');
		
		asrt( (int) $numOfFKS, 2 );
	}
}

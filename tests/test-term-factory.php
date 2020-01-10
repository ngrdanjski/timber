<?php

use Timber\Term;
use Timber\Factory\TermFactory;

class MyTerm extends Term {}
class WhacknessLevel extends Term {}
class HellaWhackTerm extends Term {}

/**
 * @group factory
 * @group terms-api
 */
class TestTermFactory extends Timber_UnitTestCase {
	public function tearDown() {
		unregister_taxonomy_for_object_type('make', 'post');
		parent::tearDown();
	}

	public function testGetTerm() {
		$tag_id = $this->factory->term->create(['name' => 'Toyota',    'taxonomy' => 'post_tag']);
		$cat_id = $this->factory->term->create(['name' => 'Chevrolet', 'taxonomy' => 'category']);

		$termFactory = new TermFactory();
		$tag				 = $termFactory->from($tag_id);
		$cat				 = $termFactory->from($cat_id);

		// Assert that all instances are of Timber\Term
		$this->assertInstanceOf(Term::class, $tag);
		$this->assertInstanceOf(Term::class, $cat);
	}

	public function testGetTermWithOverrides() {
		register_taxonomy('whackness', 'post');
		$my_class_map = function() {
			return [
				'post_tag'  => MyTerm::class,
				'category'  => MyTerm::class,
				'whackness' => WhacknessLevel::class,
			];
		};
		add_filter( 'timber/term/classmap', $my_class_map );

		$tag_id       = $this->factory->term->create(['name' => 'Toyota',        'taxonomy' => 'post_tag']);
		$cat_id       = $this->factory->term->create(['name' => 'Chevrolet',     'taxonomy' => 'category']);
		$whackness_id = $this->factory->term->create(['name' => 'Wiggity-Whack', 'taxonomy' => 'whackness']);

		$termFactory = new TermFactory();
		$tag				 = $termFactory->from($tag_id);
		$cat				 = $termFactory->from($cat_id);
		$whackness   = $termFactory->from($whackness_id);

		$this->assertInstanceOf(MyTerm::class,         $tag);
		$this->assertInstanceOf(MyTerm::class,         $cat);
		$this->assertInstanceOf(WhacknessLevel::class, $whackness);

		remove_filter( 'timber/term/classmap', $my_class_map );
	}

	public function testGetTermWithCallable() {
		register_taxonomy('whackness', 'post');
		$my_class_map = function() {
			return [
				'category'  => function() {
					return MyTerm::class;
				},
				'whackness' => function(WP_Term $term) {
					// return a special class depending on the WP_Term name
					return ($term->name === 'Hella Whack')
						? HellaWhackTerm::class
						: WhacknessLevel::class;
				}
			];
		};
		add_filter( 'timber/term/classmap', $my_class_map );

		$tag_id       = $this->factory->term->create(['name' => 'Toyota',        'taxonomy' => 'post_tag']);
		$cat_id       = $this->factory->term->create(['name' => 'Chevrolet',     'taxonomy' => 'category']);
		$whackness_id = $this->factory->term->create(['name' => 'Wiggity-Whack', 'taxonomy' => 'whackness']);
		$hella_id     = $this->factory->term->create(['name' => 'Hella Whack',   'taxonomy' => 'whackness']);

		$termFactory = new TermFactory();
		$tag         = $termFactory->from($tag_id);
		$cat         = $termFactory->from($cat_id);
		$whackness   = $termFactory->from($whackness_id);
		$hellawhack  = $termFactory->from($hella_id);

		$this->assertInstanceOf(Term::class,           $tag);
		$this->assertInstanceOf(MyTerm::class,         $cat);
		$this->assertInstanceOf(WhacknessLevel::class, $whackness);
		$this->assertInstanceOf(HellaWhackTerm::class, $hellawhack);

		remove_filter( 'timber/term/classmap', $my_class_map );
	}

	public function testFromArray() {
		$a = $this->factory->term->create(['name' => 'A', 'taxonomy' => 'post_tag']);
		$b = $this->factory->term->create(['name' => 'B', 'taxonomy' => 'post_tag']);

		$termFactory = new TermFactory();
		$res = $termFactory->from(get_terms([
			'taxonomy'   => 'post_tag',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]));

		$this->assertTrue(true, is_array($res));
		$this->assertCount(2, $res);
		$this->assertInstanceOf(Term::class, $res[0]);
		$this->assertInstanceOf(Term::class, $res[1]);
		$this->assertEquals('A', $res[0]->name);
		$this->assertEquals('B', $res[1]->name);
	}

	public function testFromArrayCustom() {
		register_taxonomy('make', 'post');
		$my_class_map = function(array $map) {
			return array_merge($map, [
				'make'  => MyTerm::class,
			]);
		};
		add_filter( 'timber/term/classmap', $my_class_map );

		$toyota = $this->factory->term->create(['name' => 'Toyota',    'taxonomy' => 'make']);
		$chevy  = $this->factory->term->create(['name' => 'Chevrolet', 'taxonomy' => 'make']);

		$termFactory = new TermFactory();
		$res = $termFactory->from(get_terms([
			'taxonomy'   => 'make',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]));

		$this->assertTrue(true, is_array($res));
		$this->assertCount(2, $res);
		$this->assertInstanceOf(MyTerm::class, $res[0]);
		$this->assertInstanceOf(MyTerm::class, $res[1]);

		remove_filter( 'timber/term/classmap', $my_class_map );
	}

	public function testFromWpTermObject() {
		$my_class_map = function(array $map) {
			return array_merge($map, [
				'make'  => MyTerm::class,
			]);
		};
		add_filter( 'timber/term/classmap', $my_class_map );

		$cat_id    = $this->factory->term->create(['name' => 'Red Herring', 'taxonomy' => 'category']);
		$toyota_id = $this->factory->term->create(['name' => 'Toyota',    'taxonomy' => 'make']);

		$cat    = get_term($cat_id);
		$toyota = get_term($toyota_id);

		$termFactory = new TermFactory();
		$this->assertInstanceOf(MyTerm::class, $termFactory->from($toyota));
		$this->assertInstanceOf(Term::class,   $termFactory->from($cat));

		remove_filter( 'timber/term/classmap', $my_class_map );
	}

	public function testFromTermQuery() {
		register_taxonomy('make', 'post');
		$my_class_map = function(array $map) {
			return array_merge($map, [
				'make'  => MyTerm::class,
			]);
		};
		add_filter( 'timber/term/classmap', $my_class_map );

		$this->factory->term->create(['name' => 'Red Herring', 'taxonomy' => 'category']);
		$toyota = $this->factory->term->create(['name' => 'Toyota',    'taxonomy' => 'make']);
		$chevy  = $this->factory->term->create(['name' => 'Chevrolet', 'taxonomy' => 'make']);

		$termFactory = new TermFactory();
		$termQuery   = new WP_Term_Query([
			'taxonomy'   => 'make',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]);

		$res = $termFactory->from($termQuery);

		$this->assertCount(2, $res);
		$this->assertInstanceOf(MyTerm::class, $res[0]);
		$this->assertInstanceOf(MyTerm::class, $res[1]);

		remove_filter( 'timber/term/classmap', $my_class_map );
	}

	public function testFromAssortedArray() {
		register_taxonomy('make', 'post');
		$my_class_map = function(array $map) {
			return array_merge($map, [
				'make'  => MyTerm::class,
			]);
		};
		add_filter( 'timber/term/classmap', $my_class_map );

		$geo_id        = $this->factory->term->create(['name' => 'Geo',        'taxonomy' => 'make']);
		$datsun_id     = $this->factory->term->create(['name' => 'Datsun',     'taxonomy' => 'make']);
		$studebaker_id = $this->factory->term->create(['name' => 'Studebaker', 'taxonomy' => 'make']);

		$termFactory = new TermFactory();

		// pass an array with an ID, a WP_Term, and a Timber\Term instance
		$res = $termFactory->from([
			$geo_id,
			get_term($datsun_id),
			$termFactory->from($studebaker_id),
		]);

		$this->assertCount(3, $res);
		$this->assertInstanceOf(MyTerm::class, $res[0]);
		$this->assertInstanceOf(MyTerm::class, $res[1]);
		$this->assertInstanceOf(MyTerm::class, $res[2]);

		remove_filter( 'timber/term/classmap', $my_class_map );
	}

	public function testFromTermQueryArray() {
		register_taxonomy('make', 'post');
		$my_class_map = function(array $map) {
			return array_merge($map, [
				'make'  => MyTerm::class,
			]);
		};
		add_filter( 'timber/term/classmap', $my_class_map );

		$this->factory->term->create(['name' => 'Red Herring', 'taxonomy' => 'category']);
		$toyota = $this->factory->term->create(['name' => 'Toyota',    'taxonomy' => 'make']);
		$chevy  = $this->factory->term->create(['name' => 'Chevrolet', 'taxonomy' => 'make']);

		$termFactory = new TermFactory();

		$res = $termFactory->from([
			'taxonomy'   => 'make',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]);

		$this->assertCount(2, $res);
		$this->assertInstanceOf(MyTerm::class, $res[0]);
		$this->assertInstanceOf(MyTerm::class, $res[1]);

		remove_filter( 'timber/term/classmap', $my_class_map );
	}
}

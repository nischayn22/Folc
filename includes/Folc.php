<?php

namespace MediaWiki\Skins\Folc;

use MediaWiki\MediaWikiServices;
use SkinMustache;
use SkinTemplate;

class Folc extends SkinMustache {
    
    /**
     * Extends the getTemplateData function to add a template key 'html-myskin-hello-world'
     * which can be rendered in skin.mustache using {{{html-myskin-hello-world}}}
     */
    public function getTemplateData() {
        global $wgTitle, $wgRequest;


        $this_page_categories = $wgTitle->getParentCategories();

        $data = parent::getTemplateData();
        $data['pagetitle'] = $wgTitle->getFullText(); // or $this->msg('msg-key')->parse();
        $data['pagetitle_smallcase'] = strtolower( $wgTitle->getFullText() );

        $data['country_page'] = false;
        $data['region_page'] = false;
        $data['sdg_page'] = false;
        $country_name = "";

        if ( $wgRequest->getText( 'action' ) == "formedit" ) {
            $data['edit_tab'] = 'active';
        } else if ( $wgRequest->getText( 'action' ) == "history" ) {
            $data['history_tab'] = 'active';
        } else if ( $wgTitle->getNamespace() == 1 ) {
            $data['discussion_tab'] = 'active';
        } else {
            $data['read_tab'] = 'active';
        }

        $categories = ['Dance', 'Art', 'Belief','Craftmanship', 'Entertainment and Recreation', 'Foodways', 'Music', 'Ritual', 'Verbal Arts and Literature' ];

        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $dbr = $lb->getConnectionRef( DB_REPLICA );

        $countries = $dbr->newSelectQueryBuilder()
            ->select( '*' )
            ->from( 'cargo__' . 'Country' )
            ->where(['_pageID > 0'] )
            ->caller( __METHOD__ )
            ->fetchResultSet();

        foreach( $countries as $country ){

             $country_pages = $dbr->newSelectQueryBuilder()       
                ->select( '*' )
                ->from( 'cargo__' . 'Articles' )
                ->where(['Country__full LIKE "%' . $country->Country . '%"'] )
                ->caller( __METHOD__ )       
                ->fetchResultSet();

            $data[$country->Continent][] = [ 'country' => $country->Country, 'count' => $country_pages->numRows() ];

            $regions = [];
            if ( !empty( $country->Regions__full ) ) {
                $regions = explode( ',', $country->Regions__full );
            }
            if ( $wgTitle->getFullText() == $country->Country ) {
                $data['country_page'] = true;
                $data['regions'] = $regions;
            }
            if ( in_array( $wgTitle->getFullText(), $regions ) ) {
                $data['region_page'] = true;
                $data['regions'] = $regions;
                $country_name = $country->Country;
                $data['country'] = $country_name;
            }
        }


        if ( $data['region_page'] ) {
            foreach( $categories as $category ){
                 $category_pages = $dbr->newSelectQueryBuilder()       
                    ->select( '*' )
                    ->from( 'cargo__' . 'Articles' )
                    ->where(['Country__full LIKE "%' . $country_name . '%"', 'Subject__full LIKE "%' . $category . '%"', 'Subject__full LIKE "%' . $wgTitle->getFullText() . '%"'] )
                    ->caller( __METHOD__ )       
                    ->fetchResultSet();

                foreach( $category_pages as $page ) {
                    $data[$category . '_filtered'][] = \Title::newFromID( $page->_pageID )->getFullText();
                }
            }
        } else if ( $data['country_page'] ) {
            foreach( $categories as $category ){

                 $category_pages = $dbr->newSelectQueryBuilder()       
                    ->select( '*' )
                    ->from( 'cargo__' . 'Articles' )
                    ->where(['Country__full LIKE "%' . $wgTitle->getFullText() . '%"', 'Subject__full LIKE "%' . $category . '%"'] )
                    ->caller( __METHOD__ )       
                    ->fetchResultSet();

                foreach( $category_pages as $page ) {
                    $data[$category . '_filtered'][] = \Title::newFromID( $page->_pageID )->getFullText();
                }
            }
        } else if ( in_array( $wgTitle->getFullText(), $categories ) ) {
            $data['category_page'] = true;

            foreach( $countries as $country ){

                $country_pages = $dbr->newSelectQueryBuilder()       
                    ->select( '*' )
                    ->from( 'cargo__' . 'Articles' )
                    ->where(['Country__full LIKE "%' . $country->Country . '%"', 'Subject__full LIKE "%' . $wgTitle->getFullText() . '%"'] )
                    ->caller( __METHOD__ )       
                    ->fetchResultSet();

                $page_list = [];
                foreach( $country_pages as $page ) {
                    $page_list[] = \Title::newFromID( $page->_pageID )->getFullText();
                }

                $data[$country->Continent . '_filtered'][] = [ 'country' => $country->Country, 'count' => $country_pages->numRows(), 'list' => $page_list ];
            }

        } else if ( array_key_exists( "Category:SDG", $this_page_categories ) ) {
           $data['sdg_page'] = true;
           $data['sdg'] = $wgTitle->getFullText();
            foreach( $categories as $category ){

                 $category_pages = $dbr->newSelectQueryBuilder()       
                    ->select( '*' )
                    ->from( 'cargo__' . 'Articles' )
                    ->where(['SDG__full LIKE "%' . $wgTitle->getFullText() . '%"', 'Subject__full LIKE "%' . $category . '%"'] )
                    ->caller( __METHOD__ )       
                    ->fetchResultSet();

                foreach( $category_pages as $page ) {
                    $data[$category . '_filtered'][] = \Title::newFromID( $page->_pageID )->getFullText();
                }
            }
        } else if ( !$wgTitle->isMainPage() && in_array( $wgTitle->getNamespace(), [ 0, 1 ] ) ) {
            $data['pagetitle'] = $wgTitle->getText(); // or $this->msg('msg-key')->parse();


            $res = $dbr->newSelectQueryBuilder()
                ->select( '*' )
                ->from( 'cargo__' . 'Articles' )
                ->where( [ '_pageID' => $wgTitle->getId() ] )
                ->caller( __METHOD__ )
                ->fetchResultSet();

            $data['article_page'] = true;
            foreach( $res as $row ) {
                if ( !empty( $row->Tags__full ) ) {
                    $data['tags'] = explode( ',', $row->Tags__full );
                }
                if ( !empty( $row->Country__full ) ) {
                    $data['countries'] = explode( ',', $row->Country__full );
                }
                if ( !empty( $row->Region__full ) ) {
                    $data['regions'] = explode( ',', $row->Region__full );
                }
                $data['sdg'] = explode( ',', $row->SDG__full );
                $data['description'] = $row->Description;
                $data['img-caption'] = $row->Image_Caption;
                if ( !empty( $row->File ) ) {
                    $file = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $row->File );
                    $data['img'] = $file->getFullUrl();
                }

                if ( !empty( $row->Subject__full ) ) {
                    $subjects = explode( ',', $row->Subject__full );

                    foreach( $subjects as $subject ) {
                        $data['subjects'][] = [
                            'subject_name_lower' => explode( ' ', trim( strtolower( $subject ) ) )[0],
                            'subject_name' => trim( ucwords( $subject ) )
                        ];
                    }
                }
            }
        } else if ( $wgTitle->isMainPage() ) {
            $data['main_page'] = true;
        } else {
            $data['other_page'] = true;
        }
        return $data;
    }
}
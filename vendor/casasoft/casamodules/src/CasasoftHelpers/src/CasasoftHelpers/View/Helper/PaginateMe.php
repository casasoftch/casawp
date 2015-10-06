<?php
namespace CasasoftHelpers\View\Helper;
 
use Zend\View\Helper\AbstractHelper;
use Zend\View\Model\ViewModel;

class PaginateMe extends AbstractHelper{ 

    public function __construct($url){
        $this->url = $url;
    }

    public function __invoke($count, $route, $params, $query, $nr_of_items = 9){
        $paginationItems = ''; 
        
        $page = (isset($query['page']) ? $query['page'] : 1);
        $ppp = (isset($query['ppp']) ? $query['ppp'] : 15);

        //PREV
        $prev_page = $page-1;
        $prev_query = $query;
        $disabled = ($prev_page > 0 ? false : true);
        if (!$disabled) {
            $prev_query['page'] = $prev_page;
        }
        $url = $this->url->__invoke($route, $params, array('query' => $prev_query));
        $paginationItems .= '<li '.($disabled ? 'class="disabled"' : '' ).'><a href="'. $url . '"><i class="fa fa-angle-left"></i></a></li>';

        //PAGES
        $cur_page_try = $page-(($nr_of_items-1)/2);
        $cur_page = round($cur_page_try < 1 ? 1 : $cur_page_try);
        $max_page = ceil($count/$ppp);
        if ($max_page == 0) {
            $max_page = 1;
        }
        if (($cur_page+$nr_of_items) > $max_page) {
            if (($max_page-$nr_of_items) > 0) {
                $cur_page = ($max_page-$nr_of_items);
            } else {
                $cur_page = 1;
            }
        }
        $cur_query = $query;
        $page_count = 0;
        for ($cp=$cur_page; $cp <= $max_page; $cp++) { 
            $cur_page = $cp;
            $page_count++;
            $cur_query['page'] = $cur_page;
            $url = $this->url->__invoke($route, $params, array('query' => $cur_query));
            $paginationItems .= '<li '.($page == $cur_page ? 'class="active"' : '').'><a href="' . $url . '">'.$cur_page.'</a></li>';
            if ($page_count > ($nr_of_items-1)) {
                break;
            }
        }

        //NEXT
        $next_page = $page+1;
        $next_query = $query;
        $disabled = ($next_page <= $max_page ? false : true);
        if (!$disabled) {
            $next_query['page'] = $next_page;
        }
        $url = $this->url->__invoke($route, $params, array('query' => $next_query));
        $paginationItems .= '<li '.($disabled ? 'class="disabled"' : '' ).'><a href="' . $url . '"><i class="fa fa-angle-right"></i></a></li>';

        return $paginationItems;
    }
}
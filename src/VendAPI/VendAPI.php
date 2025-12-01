<?php

/**
 * VendAPI
 *
 * An api for communicating with vend pos software - http://www.vendhq.com
 *
 * Requires php 5.3
 *
 * @package    VendAPI
 * @author     Bruce Aldridge <bruce@incode.co.nz>
 * @copyright  2012-2015 Bruce Aldridge
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL 3.0
 * @link       https://github.com/brucealdridge/vendapi
 */

namespace VendAPI;

spl_autoload_register(function ($class) {
    list($namespace, $classname) = array_pad(explode('\\', $class, 2), -2, null);
    if ($namespace == 'VendAPI') {
        include rtrim(__DIR__, '/').'/'.$classname . '.php';
    }
});

class VendAPI
{
    private $url;

    private $last_result_raw;
    private $last_result;

    private $requestr;

    private $debug = false;
    /**
     * Request all pages of the results, looping through returning as a single result set
     * @var boolean
     */
    public $automatic_depage = false;
    /**
     * Default outlet to use for inventory, this shouldn't need to be changed
     * @var string
     */
    public $default_outlet = 'Main Outlet';

    /**
     * If rate limiting kicks in and the retry-after date is earlier than the current system time
     * then this API will sleep for 60 seconds, otherwise an exception will be thrown.
     * The right thing to do is ensure that your system has its time synchronised with a time server.
     */
    public $allow_time_slip = false;

    /**
     * @param string $url          url of your shop eg https://shopname.vendhq.com
     * @param string $tokenType    tokenType for api
     * @param string $accessToken  accessToken for api
     * @param string $requestClass used for testing
     */
    public function __construct($url, $tokenType, $accessToken, $requestClass = '\VendAPI\VendRequest')
    {
        // trim trailing slash for niceness
        $this->url = rtrim($url, '/');

        $this->requestr = new $requestClass($url, $tokenType, $accessToken);

    }
    /**
     * turn on debuging for this class and requester class
     * @param  boolean $status
     */
    public function debug($status = true)
    {
        $this->requestr->setOpt('debug', $status);
        $this->debug = true;
    }
    public function __destruct()
    {

    }

	//************************************* API 1.0 Functions ********************************************************************
	
    public function getCustomers($options = array())
    {
        $path = '';
        if (count($options)) {
            foreach ($options as $k => $v) {
                $path .= '/'.$k.'/'.$v;
            }
        }

        return $this->apiGetCustomers($path);
    }
    /**
     * Get all products
     *
     * @param array $options .. optional
     * @return array
     */
    public function getProducts($options = array())
    {
        $path = '';
        if (count($options)) {
            foreach ($options as $k => $v) {
                $path .= '/'.$k.'/'.$v;
            }
        }

        return $this->apiGetProducts($path);
    }
	
	/**
     * Get all products pages
     *
	 * @param array $options .. optional
     * @return string
     */
    public function getProductsPages($options = array())
    {
		$path = '';
        if (count($options)) {
            foreach ($options as $k => $v) {
                $path .= '/'.$k.'/'.$v;
            }
        }
		
        return $this->apiGetProductsPages($path);
    }

    /**
     * Get all active registers
     *
     * @param array $options .. optional
     * @return array
     */
    public function getRegisters($options = array())
    {
        $path = '';
        if (count($options)) {
            foreach ($options as $k => $v) {
                $v = urlencode($v);  // ensure values with spaces etc are encoded properly
                $path .= '/'.$k.'/'.$v;
            }
        }

        return $this->apiGetRegisters($path);
    }
    
    /**
     * Get all sales
     *
     * @param array $options .. optional
     * @return array
     */
    public function getSales($options = array())
    {
        $path = '';
        if (count($options)) {
            foreach ($options as $k => $v) {
                $v = urlencode($v);  // ensure values with spaces etc are encoded properly
                $path .= '/'.$k.'/'.$v;
            }
        }

        return $this->apiGetSales($path);
    }
    /**
     * Get a single product by id
     *
     * @param string $id id of the product to get
     *
     * @return object
     */
    public function getProduct($id)
    {
        $result = $this->getProducts(array('id' => $id));
        return is_array($result) && isset($result[0]) ? $result[0] : new VendProduct(null, $this);
    }
    /**
     * Get a single customer by id
     *
     * @param string $id id of the customer to get
     *
     * @return object
     */
    public function getCustomer($id)
    {
        $result = $this->getCustomers(array('id' => $id));
        return is_array($result) && isset($result[0]) ? $result[0] : new VendCustomer(null, $this);
    }
    /**
     * Get a single sale by id
     *
     * @param string $id id of the sale to get
     *
     * @return object
     */
    public function getSale($id) {
	$result = $this->_request20('/api/2.0/sales/' . $id);
	if (!isset($result->data)) {
	        throw new \Exception("Error: Unexpected result for request");
	}
	return new VendSale($result->data, $this);
    }
	
    public function getProductsSince($date)
    {
        $result = $this->getProducts(array('since' => $date));
        return $result;
    }
    public function getSalesSince($date)
    {
        $result = $this->getSales(array('since' => $date));
        return $result;
    }
    /**
     * request a specific path from vend
     *
     * @param string $path the absolute path of the requested item (ie /api/products )
     *
     * @return object returned from vend
     */
    public function request($path, $data = null)
    {
        return $this->_request($path, $data);
    }
    private function apiGetProducts($path)
    {
        $result = $this->_request('/api/products'.$path);
        if (!isset($result->products) || !is_array($result->products)) {
            throw new Exception("Error: Unexpected result for request");
        }
		        
		$products = array();
        foreach ($result->products as $product) {
            $products[] = new VendProduct($product, $this);
        }

        return $products;
    }
	private function apiGetProductsPages($path)
    {
        $result = $this->_request('/api/products'.$path);
        if (!isset($result->products) || !is_array($result->products)) {
            throw new Exception("Error: Unexpected result for request");
        }
		  
        return $result->pagination;
    }
		
    private function apiGetCustomers($path)
    {
        $result = $this->_request('/api/customers'.$path);
        if (!isset($result->customers) || !is_array($result->customers)) {
            throw new Exception("Error: Unexpected result for request");
        }
        $customers = array();
        foreach ($result->customers as $cust) {
            $customers[] = new VendCustomer($cust, $this);
        }

        return $customers;
    }
    private function apiGetSales($path)
    {
        $result = $this->_request20('/api/2.0/sales'.$path);
        if (!isset($result->data) || !is_array($result->data)) {
            throw new Exception("Error: Unexpected result for request");
        }
        $sales = array();
        foreach ($result->data as $s) {
            $sales[] = new VendSale($s, $this);
        }

        return $sales;
    }
    /**
     * @param $path
     * @return array
     * @throws Exception
     */
    private function apiGetRegisters($path)
    {
        $result = $this->_request('/api/registers'.$path);
        if (!isset($result->registers) || !is_array($result->registers)) {
            throw new Exception("Error: Unexpected result for request");
        }
        $sales = array();
        foreach ($result->register_sales as $s) {
            $sales[] = new VendSale($s, $this);
        }

        return $sales;
    }

    /**
     * Save vendproduct object to vend
     * @param object $product
     * @return object
     */
    public function saveProduct($product)
    {
        $result = $this->_request('/api/products', $product->toArray());

        return new VendProduct($result->product, $this);
    }
    /**
     * Save customer object to vend
     * @param object $cust
     * @return object
     */
    public function saveCustomer($cust)
    {
        $result = $this->_request('/api/customers', $cust->saveArray());

        return new VendCustomer($result->customer, $this);
    }
    /**
     * Save sale object to vend
     * @param object $sale
     * @return object
     */
    public function saveSale($sale)
    {
        $result = $this->_request('/api/register_sales', $sale->toArray());

        return new VendSale($result->register_sale, $this);
    }
    
	/**
     * make request to the vend api
     *
     * @param string  $path   the url to request
     * @param array   $data   optional - if sending a post request, send fields through here
     * @param boolean $depage do you want to grab and merge page results? .. will only depage on first page
     *
     * @return object variable result based on request
     */
    private function _request($path, $data = null, $depage = null)
    {
        $depage = $depage === null ? $this->automatic_depage : $depage;
        if ($data !== null) {
            // setup for a post

            $rawresult = $this->requestr->post($path, json_encode($data));

        } else {
            // reset to a get
            $rawresult = $this->requestr->get($path);
        }
	
        $result = json_decode($rawresult);
        if ($result === null) {
            throw new Exception("Error: Recieved null result from API");
        }

        // Check for 400+ error:
        if($this->requestr->http_code >= 400) {
            if($this->requestr->http_code == 429) {    // Too Many Requests
                $retry_after = strtotime($result->{'retry-after'});
                if($retry_after < time()) {
                    if($this->allow_time_slip) {
                        // The date on the current machine must be out of sync ... sleep for a minute to give the API time to cool down
                        sleep(60);
                    } else {
                        throw new Exception("Rate limit hit on API yet retry-after time given is in the past. Please check time of local system. Set \$allow-time-slip to true to work around this problem");
                    }
                }
                if($this->debug) {
                    echo "Vend API rate limit hit\n";
                    echo "Time now on local system is ".date('r',time())."\n";
                    echo "Sleeping until ".date('r', $retry_after)." (as advised by Vend API) ";
                }
                while(time() < $retry_after) {
                    sleep(1);
                    if($this->debug) { echo "."; }
                }

                // We've given the Vend API time to cool down - retry the original request:
                return $this->_request($path, $data, $depage);
            }
            throw new Exception("Error: Unexpected HTTP ".$this->requestr->http_code." result from API");
        }

        if ($depage && isset($result->pagination) && $result->pagination->page == 1) {
            for ($i=2; $i <= $result->pagination->pages; $i++) {
                $paged_result = $this->_request(rtrim($path, '/').'/page/'.$i, $data, false);
                $result = $this->_mergeObjects($paged_result, $result);
            }
        }

        if ($result && isset($result->error)) {
            throw new Exception($result->error .' : '. $result->details);
        }

        if ($this->debug) {
            $this->last_result_raw = $rawresult;
            $this->last_result = $result;
        }

        return $result;
    }
	
	/**
     * merge two objects when depaginating results
     *
     * @param object $obj1 original object to overwrite / merge
     * @param object $obj2 secondary object
     *
     * @return object       merged object
     */
    private function _mergeObjects($obj1, $obj2)
    {
        $obj3 = $obj1;
        foreach ($obj2 as $k => $v) {
            if (is_array($v) && isset($obj3->$k) && is_array($obj3->$k)) {
                $obj3->$k = array_merge($obj3->$k, $v);
            } else {
                $obj3->$k = $v;
            }
        }
        return $obj3;
    }
	
	//************************************* API 2.0 Functions ********************************************************************
	
	/**
     * Get all products 2.0
     *
	 * @param array $options .. optional
     * @return string
     */
    public function getProducts20($options = array(),$single = null)
    {
		if(!isset($single)){
			$path = '?';
			if (count($options)) {
				foreach ($options as $k => $v) {
					$path .= '&'.$k.'='.$v;
				}
			}
		} else {
			$path = $single;
		}
        return $this->apiGetProducts20($path);
    }
	
	/**
     * Get a single product by id 2.0
     *
     * @param string $id id of the product to get
     *
     * @return object
     */
    public function getProduct20($id)
    {
        $result = $this->getProducts20(null,$id);
        return $result;
    }
	
	/**
     * Get all customers 2.0
     *
     * @return object
     */
    public function getCustomers20($options = array(),$single = null)
    {
		if(!isset($single)){
			$path = '?';
			if (count($options)) {
				foreach ($options as $k => $v) {
					$path .= '&'.$k.'='.$v;
				}
			}
		} else {
			$path = $single;
		}
        return $this->apiGetCustomers20($path);
    }
	
	/**
     * Save customer object to vend
     * @param object $cust
     * @return object
     */
    public function saveCustomer20($id,$options = array())
    {
        return $this->apisaveCustomer20($options,$id);
    }
	
	/**
     * Save customer object to vend
     * @param object $cust
     * @return object
     */
    public function addCustomer20($options = array())
    {
        return $this->apiaddCustomer20($options);
    }
	
     /**
     * Get all brands 2.0
     *
     * @param array $options .. optional
     * @return object
     */
    public function getBrands20($options = array(),$single = null)
    {
		if(!isset($single)){
			$path = '?';
			if (count($options)) {
				foreach ($options as $k => $v) {
					$path .= '&'.$k.'='.$v;
				}
			}
		} else {
			$path = $single;
		}
        return $this->apiGetBrands20($path);
    }

    /**
     * Get all tags 2.0
     *
     * @param array $options .. optional
     * @return object
     */
    public function getTags20($options = array(),$single = null)
    {
		if(!isset($single)){
			$path = '?';
			if (count($options)) {
				foreach ($options as $k => $v) {
					$path .= '&'.$k.'='.$v;
				}
			}
		} else {
			$path = $single;
		}
        return $this->apiGetTags20($path);
    }

    /**
     * Get all categories 2.0
     *
	 * @param array $options .. optional
     * @return string
     */
    public function getCategories20()
    {
        return $this->apiGetCategories20();
    }
	
	/**
     * Get all Pricebooks 2.0
     *
	 * @param array $options .. optional
     * @return string
     */
    public function getPricebooks20($options = array(),$single = null)
    {
		if(!isset($single)){
			$path = '?';
			if (count($options)) {
				foreach ($options as $k => $v) {
					$path .= '&'.$k.'='.$v;
				}
			}
		} else {
			$path = $single;
		}
        return $this->apiGetPricebooks20($path);
    }
	
	/**
     * Get all Pricebooks book 2.0
     *
	 * @param array $options .. optional
     * @return string
     */
    public function getPricebooksbook20($options = array(),$single = null)
    {
		if(!isset($single)){
			$path = '?';
			if (count($options)) {
				foreach ($options as $k => $v) {
					$path .= '&'.$k.'='.$v;
				}
			}
		} else {
			$path = $single;
		}
        return $this->apiGetPricebooksbook20($path);
    }

     /**
     * Get all Promotions 2.0
     *
     * @param array $options .. optional
     * @return string
     */
    public function getPromotions20($options = array(),$single = null)
    {
		if(!isset($single)){
			$path = '?';
			if (count($options)) {
				foreach ($options as $k => $v) {
					$path .= '&'.$k.'='.$v;
				}
			}
		} else {
			$path = '/'.$single;
		}
        return $this->apiGetPromotions20($path);
    }

	/**
     * Get Promo Codes 2.0
     *
     * @param string $promoID
     * @return array
     */
    public function getPromoCodes20($promoID)
    {
        return $this->apiGetPromotions20('/'.$promoID.'/promocodes');
    }
	

	/**
     * Get inventories 2.0
     *
     */
	public function getInventories($options = array())
    {
		$path = '?';
			if (count($options)) {
				foreach ($options as $k => $v) {
					$path .= '&'.$k.'='.$v;
				}
			}
         $result = $this->_request20('/api/2.0/inventory'.$path);
        if (!isset($result->data)) {
            throw new Exception("Error: Unexpected result for request");
        }		  
        return $result;
    }
	
	/**
     * Get a single product inventory by id 2.0
     *
     * @param string $id id of the product to get
     *
     * @return object
     */
	public function getInventory20($id)
    {
        $result = $this->_request20('/api/2.0/products/'.$id.'/inventory');
        if (!isset($result->data)) {
			echo 'Composite or Deleted Product?';
            //throw new Exception("Error: Unexpected result for request");
        } else {		  
        	return $result;
		}
    }
	
	
	//API Requests 
	private function apiGetProducts20($path)
    {
        $result = $this->_request20('/api/2.0/products/'.$path);
        if (!isset($result->data)) {
            throw new Exception("Error: Unexpected result for request");
        }		  
        return $result;
    }
	
	private function apiGetCustomers20($path)
    {
        $result = $this->_request20('/api/2.0/customers/'.$path);
        if (!isset($result->data)) {
            throw new Exception("Error: Unexpected result for request");
        }		  
        return $result;
    }
	
	private function apiGetBrands20($path)
    {
        $result = $this->_request20('/api/2.0/brands/'.$path);
        if (!isset($result->data)) {
            throw new Exception("Error: Unexpected result for request");
        }		  
        return $result;
    }

    private function apiGetTags20($path)
    {
        $result = $this->_request20('/api/2.0/tags/'.$path);
        if (!isset($result->data)) {
            throw new Exception("Error: Unexpected result for request");
        }		  
        return $result;
    }

    private function apiGetCategories20()
    {
        $result = $this->_request20('/api/2.0/product_categories');
        if (!isset($result->data)) {
            throw new Exception("Error: Unexpected result for request");
        }		  
        return $result;
    }
	
	private function apiGetPricebooks20($path)
    {
        $result = $this->_request20('/api/2.0/price_book_products/'.$path);
        if (!isset($result->data)) {
            throw new Exception("Error: Unexpected result for request");
        }		  
        return $result;
    }

	private function apiGetPromotions20($path)
    {
        $result = $this->_request20('/api/2.0/promotions'.$path);
        if (!isset($result->data)) {
            throw new Exception("Error: Unexpected result for request");
        }		  
        return $result;
    }
	
	private function apiGetPricebooksbook20($path)
    {
        $result = $this->_request20('/api/2.0/price_books/'.$path);
        if (!isset($result->data)) {
            //throw new Exception("Error: Unexpected result for request");
        }		  
        return $result;
    }
	
	private function apisaveCustomer20($options,$id)
    {
        $result = $this->_request20('/api/2.0/customers/'.$id,$options,1);		
        if (!isset($result->data)) {
          throw new Exception("Error: Unexpected result for request");
        }		  
        return $result;
    }
	
	private function apiaddCustomer20($options)
    {
        $result = $this->_request20('/api/2.0/customers',$options,null);		
        /*if (!isset($result->data)) {
          throw new Exception("Error: Unexpected result for request");
        }*/		  
        return $result;
    }

	public function putRequest($path, $data = null) {
	    // This will call the underlying Curl wrapper’s put() method.
	    $rawresult = $this->requestr->put($path, json_encode($data));
	    $result = json_decode($rawresult);
	    if ($result === null) {
	        throw new Exception("Error: Received null result from API");
	    }
	    if ($this->requestr->http_code >= 400) {
	        throw new Exception("Error: Unexpected HTTP ".$this->requestr->http_code." result from API: " . $rawresult);
	    }
	    return $result;
	}

	public function postRequest($path, $data = null) {
	    $rawresult = $this->requestr->post($path, json_encode($data));
	    $result = json_decode($rawresult);
	    if ($result === null) {
	        throw new Exception("Error: Received null result from API");
	    }
	    if ($this->requestr->http_code >= 400) {
	        throw new Exception("Error: Unexpected HTTP ".$this->requestr->http_code." result from API: " . $rawresult);
	    }
	    return $result;
	}
		
	/**
	 * Update product name and/or description in Lightspeed
	 * @param string $productId Lightspeed product UUID
	 * @param array $data ['name' => '...', 'description' => '...']
	 * @return object API response
	 */
	public function updateProduct20($productId, array $data) {
	    // Lightspeed API 2.1 requires data in 'common' section for name/description
	    // as these are shared across all variants in a product family
	    $payload = [
	        'common' => $data
	    ];
	    return $this->putRequest('/api/2.1/products/' . $productId, $payload);
	}

	/**
	 * Update brand name in Lightspeed
	 * @param string $brandId Lightspeed brand UUID
	 * @param array $data ['name' => '...']
	 * @return object API response
	 */
	public function updateBrand20($brandId, array $data) {
	    return $this->putRequest('/api/2.0/brands/' . $brandId, $data);
	}

	/**
	 * Update category name in Lightspeed using bulk endpoint
	 * @param string $categoryId Lightspeed category UUID
	 * @param array $data ['name' => '...']
	 * @return object API response
	 */
	public function updateCategory20($categoryId, array $data) {
	    // Lightspeed API 2.0 requires bulk endpoint for category updates
	    $payload = [
	        'categories' => [
	            array_merge(['id' => $categoryId], $data)
	        ]
	    ];
	    return $this->postRequest('/api/2.0/product_categories/bulk', $payload);
	}
	
	/**
     * make request to the vend api version 2.0
     *
     * @param string  $path   the url to request
     * @param array   $data   optional - if sending a post request, send fields through here
     * @param boolean $depage do you want to grab and merge page results? .. will only depage on first page
     *
     * @return object result based on request
     */
    private function _request20($path, $data = null,$put = null)
    {
		//return json_encode($data);
		if ($data !== null && $put!==null) {
            // setup for a post
            $rawresult = $this->requestr->put($path, json_encode($data));

        } elseif ($data !== null && $put==null) {
            // setup for a post
            $rawresult = $this->requestr->post($path, json_encode($data));

        } else {
            // reset to a get
            $rawresult = $this->requestr->get($path);
        }
				
        $result = json_decode($rawresult);
        if ($result === null) {
            throw new Exception("Error: Recieved null result from API");
        }

        if ($result && isset($result->error)) {
            throw new Exception($result->error);
        }

        if ($this->debug) {
            $this->last_result_raw = $rawresult;
            $this->last_result = $result;
        }

        return $result;
    }
}



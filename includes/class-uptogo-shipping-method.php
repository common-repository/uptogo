<?php

/**
 * Shipping method class.
 * 
 * @since       1.0.0
 * 
 * @link        https://docs.woocommerce.com/document/shipping-method-api
 */
class Uptogo_Shipping_Method extends WC_Shipping_Method {

  /**
   * Constructor function for shipping method class.
   * 
   * @since     1.0.0
   * 
   * @return    void                                  Nothing to return.
   */
  public function __construct () {
    $this->id                 = "uptogo";
    $this->method_title       = __("Uptogo", "uptogo");
    $this->method_description = __("E-commerce delivery with real-time tracking.", "uptogo");
    $this->init();
  }

  /**
   * Function that help constructor in initialization.
   * 
   * @since     1.0.0
   * 
   * @final
   * 
   * @return    void                                  Nothing to return.
   */
  final private function init () : void {
    $this->init_form_fields();
    $this->init_settings();
    $this->init_actions();
  }

  /**
   * Function that initializes the fields of administrative settings form.
   * 
   * @since     1.0.0
   * 
   * @final
   * 
   * @return    void                                  Nothing to return.
   */
  final public function init_form_fields () : void {
    $this->form_fields    = [
      "api_key"           => [
        "title"           => __("Access key", "uptogo"),
        "description"     => __("View your access key in our app settings.", "uptogo"),
        "type"            => "text"
      ],
      "store_id"          => [
        "title"           => __("Store identifier", "uptogo"),
        "description"     => __("Information obtained automatically through the access key.", "uptogo"),
        "type"            => "text"
      ],
      "store_location"    => [
        "title"           => __("Store location", "uptogo"),
        "description"     => __("Information obtained automatically through the access key.", "uptogo"),
        "type"            => "text"
      ],
    ];
  }

  /**
   * Function that initializes the shipping method actions.
   * 
   * @since     1.0.0
   * 
   * @final
   * 
   * @return    void                                  Nothing to return.
   */
  final private function init_actions () : void {
    add_action(
      "woocommerce_update_options_shipping_uptogo",
      [
        $this,
        "handle_woocommerce_update_options_shipping_uptogo"
      ]
    );
    add_action(
      "woocommerce_order_action_uptogo_delivery_create",
      [
        $this,
        "handle_woocommerce_order_action_uptogo_delivery_create"
      ]
    );
    add_action(
      "woocommerce_order_action_uptogo_delivery_cancel",
      [
        $this,
        "handle_woocommerce_order_action_uptogo_delivery_cancel"
      ]
    );
  }

  /**
   * Function that handle updates in the administrative settings form.
   * 
   * @since     1.0.0
   * 
   * @link      https://docs.woocommerce.com/document/introduction-to-hooks-actions-and-filters
   * 
   * @final
   * 
   * @return    void                                  Nothing to return.
   */
  final public function handle_woocommerce_update_options_shipping_uptogo () : void {
    if (count($customer = $this->get_customer($this->get_post_data()["woocommerce_uptogo_api_key"])) > 0) {
      $this->set_post_data(
        array_merge(
          $this->get_post_data(),
          [
            "woocommerce_uptogo_store_id"       => $customer["Id"],
            "woocommerce_uptogo_store_location" => $customer["Location"]
          ]
        )
      );
    } else {
      $_POST = [];
      $this->set_post_data([]);
      $this->add_error(__("Access key not found.", "uptogo"));
      $this->display_errors();
    }
    parent::process_admin_options();
  }

  /**
    * Function that handle delivery create action.
    * 
    * @since     1.0.0
    * 
    * @link      https://docs.woocommerce.com/document/introduction-to-hooks-actions-and-filters
    * 
    * @final
    * 
    * @param     WC_Order                $order        WooCommerce order.
    * 
    * @return    void                                  Nothing to return.
    */
  final public function handle_woocommerce_order_action_uptogo_delivery_create (
    WC_Order    $order
  ) : void {
    $shipping_method = get_shipping_method($order);
    if ($this->allow_delivery_create($order) === true) {
      $this->get_place_suggestions(
        $order->get_shipping_postcode(),
        function ($place_suggestions) use ($order, $shipping_method) {
          $this->get_place_details(
            $place_suggestions,
            function ($place_details) use ($order, $shipping_method) {
              $this->get_rate(
                $shipping_method->get_meta(__("Inventory", "uptogo")),
                $shipping_method->get_meta(__("Proposal", "uptogo")),
                function ($rate) use ($order, $shipping_method, $place_details) {
                  $this->delivery_create(
                    $order,
                    $rate,
                    $place_details,
                    function ($delivery_order_id) use ($order, $shipping_method) {
                      $order->add_order_note(
                        sprintf(
                          /* translators: %s: Link to a delivery */
                          __("Delivery number %s has been requested.", "uptogo"),
                          $this->get_delivery_link($delivery_order_id)
                        )
                      );
                      $shipping_method->add_meta_data(__("Request", "uptogo"), $delivery_order_id, true);
                      $shipping_method->save_meta_data();
                    }
                  );
                }
              );
            }
          );
        }
      );
    }
  }

  /**
    * Function that handle delivery cancel action.
    * 
    * @since     1.0.0
    * 
    * @link      https://docs.woocommerce.com/document/introduction-to-hooks-actions-and-filters
    * 
    * @final
    * 
    * @param     WC_Order                $order        WooCommerce order.
    * 
    * @return    void                                  Nothing to return.
    */
  final public function handle_woocommerce_order_action_uptogo_delivery_cancel (
    WC_Order    $order
  ) : void {
    $shipping_method = get_shipping_method($order);
    if ($this->allow_delivery_cancel($order) === true) {
      $this->delivery_cancel(
        $shipping_method->get_meta(__("Request", "uptogo")),
        function () use ($order, $shipping_method) {
          $order->add_order_note(
            sprintf(
              /* translators: %s: Link to a delivery */
              __("Delivery number %s has been cancelled.", "uptogo"),
              $this->get_delivery_link($shipping_method->get_meta(__("Request", "uptogo")))
            )
          );
          $shipping_method->delete_meta_data(__("Request", "uptogo"));
          $shipping_method->save_meta_data();
      });
    }
  }

  /**
    * Function that checks if a delivery can be created.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @param     WC_Order                $order        WooCommerce order.
    * 
    * @return    boolean                               Inform if a delivery can be created.
    */
  final private function allow_delivery_create (
    WC_Order    $order
  ) : bool {
    $shipping_method = get_shipping_method($order);
    return (
      $this->is_settings_valid() === true
      &&
      strlen($order->get_shipping_postcode()) > 0
      &&
      $shipping_method !== null
      &&
      $shipping_method->meta_exists(__("Request", "uptogo")) === false
      &&
      $shipping_method->meta_exists(__("Inventory", "uptogo")) === true
      &&
      $shipping_method->meta_exists(__("Proposal", "uptogo")) === true
    );
  }

  /**
    * Function that checks if a delivery can be cancelled.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @param     WC_Order                $order        WooCommerce order.
    * 
    * @return    boolean                               Inform if a delivery can be cancelled.
    */
  final private function allow_delivery_cancel (
    WC_Order    $order
  ) : bool {
    $shipping_method = get_shipping_method($order);
    return (
      $this->is_settings_valid() === true
      &&
      $shipping_method !== null
      &&
      $shipping_method->meta_exists(__("Request", "uptogo")) === true
    );
  }

  /**
    * Function that returns the base url for API according to the environment.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @return    string                                Base URL for API.
    */
  final private function get_base_url_api () : string {
    if (getenv("UPTOGO_ENV") === "dev") {
      return "http://localhost/uptogo/api";
    }
    return "https://api.uptogo.com.br";
  }

  /**
    * Function that returns the base url for app according to the environment.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @return    string                                Base URL for app.
    */
  final private function get_base_url_app () : string {
    if (getenv("UPTOGO_ENV") === "dev") {
      return "http://localhost:3000";
    }
    return "https://web.uptogo.com.br";
  }

  /**
    * Function that returns the delivery link based on its identifier.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @param     string                  $id           Identifier of a delivery.
    * 
    * @return    string                                HTML formatted delivery link.
    */
  final private function get_delivery_link (
    string      $id
  ) : string {
    return "<a href=\"{$this->get_base_url_app()}/acompanhar/lote/{$id}\">{$id}</a>";
  }

  /**
    * Function that returns the content-type header for request.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @return    string                                HTTP content-type request header.
    */
  final private function get_request_content_type () : string {
    return "Content-Type: application/json";
  }

  /**
    * Function that returns the authorization header for request.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @return    string                                HTTP authorization request header.
    */
  final private function get_request_authorization () : string {
    return "Authorization: Basic {$this->settings["api_key"]}";
  }

  /**
    * Function that makes an HTTP request and returns the result.
    * 
    * @since     1.0.0
    * 
    * @link      https://api.uptogo.com.br
    * @link      https://developer.mozilla.org/en-US/docs/Web/HTTP
    * 
    * @final
    * 
    * @param     string                  $method       Request method.
    * @param     string                  $path         Path part of the request URI.
    * @param     array                   $parameters   URI query parameters.
    * @param     array                   $body         Body content.
    * 
    * @return    array                                 HTTP request result.
    */
  final private function request (
    string      $method,
    string      $path,
    array       $parameters = [],
    array       $body       = []
  ) : array {
    if (($contents = file_get_contents(
      "{$this->get_base_url_api()}/{$path}?" . http_build_query($parameters),
      false,
      stream_context_create([
        "http" => [
          "content" => json_encode($body),
          "header"  => implode("\r\n", [
            $this->get_request_content_type(),
            $this->get_request_authorization()
          ]),
          "method"  => $method
        ]
      ])
    )) !== false) {
      if (is_array($decoded = json_decode($contents, true))) {
        return $decoded;
      }
      return [$decoded];
    }
    return [];
  }

  /**
    * Function that returns a customer based on its API key.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @param     string                  $api_key      Customer API key.
    * 
    * @return    array                                 Customer found.
    */
  final private function get_customer (
    string      $api_key
  ) : array {
    return $this->request("GET", "Cliente.php/by_api_key", ["api_key" => $api_key]);
  }

  /**
    * Function that conditionally pass place suggestions to a callback based on a postal code.
    * 
    * @since     1.0.0
    * 
    * @link      https://api.uptogo.com.br/#local-sugest%C3%B5es-get
    * 
    * @final
    * 
    * @param     string                  $postcode     Postal Code.
    * @param     callable                $callback     Function that accepts a collection of place suggestions.
    * 
    * @return    void                                  Nothing to return.
    */
  final private function get_place_suggestions (
    string      $postcode,
    callable    $callback
  ) : void {
    if (count($place_suggestions = $this->request("GET", "placeautocompletes", ["input" => $postcode])) > 0) {
      $callback($place_suggestions);
    }
  }

  /**
    * Function that conditionally pass place details to a callback based on a place suggestion.
    * 
    * @since     1.0.0
    * 
    * @link      https://api.uptogo.com.br/#local-detalhes-get
    * 
    * @final
    * 
    * @param     array                   $suggestions  Collection of place suggestions.
    * @param     callable                $callback     Function that accepts a place details.
    * 
    * @return    void                                  Nothing to return.
    */
  final private function get_place_details (
    array       $suggestions,
    callable    $callback
  ) : void {
    if (count($place_details = $this->request("GET", "placedetails", [
      "input" => $suggestions[0]["id"]
    ])) > 0) {
      $callback($place_details);
    }
  }

  /**
    * Function that conditionally pass directions to a callback based on a place details.
    * 
    * @since     1.0.0
    * 
    * @link      https://api.uptogo.com.br/#dire%C3%A7%C3%B5es-obter-post
    * 
    * @final
    * 
    * @param     array                   $details      Place details.
    * @param     callable                $callback     Function that accepts directions.
    * 
    * @return    void                                  Nothing to return.
    */
  final private function get_directions (
    array       $place_details,
    callable    $callback
  ) : void {
    if (count($directions = $this->request("POST", "directions", [], [
      "Pontos" => [
        0 => $this->format_store_location("Latitude", "Longitude"),
        1 => $place_details["Localizacao"]
      ]
    ])) > 0) {
      $callback($directions);
    }
  }

  /**
    * Function that create a delivery and pass a identifier to a callback when the operation succeeds.
    * 
    * @since     1.0.0
    * 
    * @link      https://api.uptogo.com.br/#pedidos-criar-post
    * 
    * @final
    * 
    * @param     WC_Order                $order        WooCommerce order to extract information.
    * @param     array                   $rate         Collection of rate properties.
    * @param     array                   $place        Collection of place details.
    * @param     callable                $callback     Function that accepts a delivery identifier.
    * 
    * @return    void                                  Nothing to return.
    */
  final private function delivery_create (
    WC_Order    $order,
    array       $rate,
    array       $place,
    callable    $callback
  ) : void {
    if (count($delivery_order = $this->request("POST", "Pedido.php/criarEcommerce", [], [
      "Pedido"            => [
        "clienteId"       => $this->settings["store_id"],
        "CotacaoId"       => $rate["Id"],
        "Distancia"       => 0,
        "Ecommerce"       => true,
        "localCliente"    => $this->format_store_location("lat", "lng"),
        "MetodoPagamento" => 3,
        "pontos"          => [[
          "Label"         => "0",
          "Invert"        => false,
          "Tarefa"        => $this->format_assignment($order),
          "NomeContato"   => $this->format_contact_name($order),
          "Notificar"     => $this->sanitize_value($order->get_billing_email(), null),
          "Localizacao"   => $place["Localizacao"],
          "Endereco"      => array_merge($place["Endereco"], [
            "Numero"      => $this->extract_number_from_address($order->get_shipping_address_1()),
            "Complemento" => $this->sanitize_value($order->get_shipping_address_2(), null)
          ])
        ]],
        "Tempo"           => $rate["Prazo"] * 24 * 60,
        "Valor"           => $rate["Preco"]
      ]
    ])) > 0 && $delivery_order["sucesso"]) {
      $callback($delivery_order["id"]);
    }
  }

  /**
    * Function that cancel a delivery and executes a callback when the operation succeeds.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @param     string                  $id           Delivery identifier.
    * @param     callable                $callback     Function that executes when the operation succeeds.
    * 
    * @return    void                                  Nothing to return.
    */
  final private function delivery_cancel (
    string      $id,
    callable    $callback
  ) : void {
    if (count($result = $this->request("POST", "Pedido.php/cancelarLote", [], ["id" => $id])) > 0 && $result[0]) {
      $callback();
    }
  }

  /**
    * Function that gets a specific rate and pass it to a callback when the operation succeeds.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @param     string                  $request_id   Identifier of a delivery request.
    * @param     string                  $rate_id      Identifier of a rate.
    * @param     callable                $callback     Function that accepts a rate.
    * 
    * @return    void                                  Nothing to return.
    */
  final private function get_rate (
    string      $request_id,
    string      $rate_id,
    callable    $callback
  ) : void {
    if (count(($rates = $this->request(
      "GET",
      "solicitacaoentregas/{$request_id}/cotacaos/{$rate_id}"))["results"]
    ) > 0) {
      $callback($rates["results"][0]);
    }
  }

  /**
    * Function that gets a collection of rates and pass it to a callback when the operation succeeds.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @param     string                  $request_id   Identifier of a delivery request.
    * @param     callable                $callback     Function that accepts a collection of rates.
    * 
    * @return    void                                  Nothing to return.
    */
  final private function get_rates (
    string      $request_id,
    callable    $callback
  ) : void {
    if (count(($rates = $this->request("GET", "solicitacaoentregas/{$request_id}/cotacaos"))["results"]) > 0) {
      $callback(array_map(function ($rate) use ($request_id) {
        return [
          "calc_tax"  => "per_order",
          "cost"      => $rate["Preco"],
          "id"        => $rate["Modalidade"]["Id"],
          "label"     => $this->format_rate_label($rate),
          "meta_data" => [
            __("Inventory", "uptogo") => $request_id,
            __("Proposal", "uptogo")  => $rate["Id"],
            __("Warning", "uptogo")   => $rate["Modalidade"]["Comentario"],
          ]
        ];
      }, $rates["results"]));
    }
  }

  /**
    * Function that create a delivery request and pass its identifier to a callback when the operation succeeds.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @param     array                   $place        Collection of place details.
    * @param     array                   $directions   Collection of directions properties.
    * @param     callable                $callback     Function that accepts a delivery request identifier.
    * 
    * @return    void                                  Nothing to return.
    */
  final private function delivery_request_create (
    array       $place,
    array       $directions,
    callable    $callback
  ) : void {
    if (count($delivery_request = $this->request("POST", "solicitacaoentregas", ["return" => "true"], [
      "Carregadores"    => 0,
      "CepDestino"      => $place["Endereco"]["Cep"],
      "CepOrigem"       => 0,
      "ClienteId"       => $this->settings["store_id"],
      "Distancia"       => $directions["Distancia"],
      "PagarNoDestino"  => false,
      "Pontos"          => 1,
      "Retirar"         => true,
      "Tempo"           => $directions["Tempo"],
      "TipoVeiculo"     => 0
    ])) > 0) {
      $callback($delivery_request["Id"]);
    }
  }

  /**
    * Function that create merchandises and executes a callback when the operation succeeds.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @param     array                   $place        Collection of place details.
    * @param     array                   $directions   Collection of directions properties.
    * @param     callable                $callback     Function that executes when the operation succeeds.
    * 
    * @return    void                                  Nothing to return.
    */
  final private function merchandises_create (
    array       $package,
    string      $request_id,
    callable    $callback
  ) : void {
    foreach ($package["contents"] as $key => $content) {
      $this->request("POST", "solicitacaoentregas/{$request_id}/mercadorias", [
        "return" => "true"
      ], $this->format_merchandise($content));
    }
    $callback();
  }

  /**
    * Function that formats the delivery time.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @param     int                     $days         Delivery time in days.
    * 
    * @return    string                                Formatted delivery time.
    */
  final private function format_delivery_time (
    int         $days
  ) : string {
    if ($days === 0) {
      return __("Immediate delivery", "uptogo");
    }
    return sprintf(
      /* translators: %d: Delivery time in days */
      _n("%d day", "%d days", $days, "uptogo"),
      number_format_i18n($days)
    );
  }

  /**
    * Function that formats the contact name.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @param     WC_Order                $order        WooCommerce order to extract information.
    * 
    * @return    string                                Formatted contact name.
    */
  final private function format_contact_name (
    WC_Order    $order
  ) : ?string {
    return $this->sanitize_value(
      "{$order->get_shipping_first_name()} {$order->get_shipping_last_name()} {$order->get_billing_phone()}",
      null
    );
  }

  /**
    * Function that formats the assignment.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @param     WC_Order                $order        WooCommerce order to extract information.
    * 
    * @return    string                                Formatted assignment.
    */
  final private function format_assignment (
    WC_Order    $order
  ) : ?string {
    return $this->sanitize_value($order->get_customer_note(), __("Deliver goods to customer.", "uptogo"));
  }

  /**
    * Function that formats the rate label.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @param     array                   $rate         Rate to extract informations.
    * 
    * @return    string                                Formatted rate label.
    */
  final private function format_rate_label (
    array       $rate
  ) : string {
    return "Uptogo - {$rate["Modalidade"]["Nome"]} ({$this->format_delivery_time($rate["Prazo"])})";
  }

  /**
    * Function that formats the customer location.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @param     string                  $label_lat    Label for latitude.
    * @param     string                  $label_lng    Label for longitude.
    * 
    * @return    string                                Formatted customer location.
    */
  final private function format_store_location (
    string      $label_lat,
    string      $label_lng
  ) : array {
    $store_location = explode(",", $this->settings["store_location"]);
    return [
      $label_lat  => $store_location[0],
      $label_lng  => $store_location[1]
    ];
  }

  /**
    * Function that extracts numbers from address.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @param     string                  $address      Address that can contain a number.
    * 
    * @return    string                                Number extracted from address.
    */
  final private function extract_number_from_address (
    string      $address
  ) : ?string {
    if (preg_match("/\d+\w?/m", $address, $match)) {
      return implode("/", $match);
    }
    return null;
  }

  /**
    * Function that sanitizes a value.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @param     string                  $value        Value to sanitize.
    * @param     string                  $default      Default value returned on failure.
    * 
    * @return    string                                Sanitized value.
    */
  final private function sanitize_value (
    string      $value,
    ?string     $default
  ) : ?string {
    if ($value !== null && strlen($value) > 0 && strlen(str_replace(" ", "", $value)) > 0) {
      return $value;
    }
    return $default;
  }

  /**
    * Function that formats a merchandise.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @param     array                   $merchandise  Merchandise to format.
    * 
    * @return    array                                 Formatted merchandise.
    */
  final private function format_merchandise (
    array       $merchandise
  ) : array {
    return [
      "Altura"        => $this->sanitize_value($merchandise["data"]->get_height(), 1),
      "Comprimento"   => $this->sanitize_value($merchandise["data"]->get_length(), 1),
      "Largura"       => $this->sanitize_value($merchandise["data"]->get_width(), 1),
      "Nome"          => $this->sanitize_value($merchandise["data"]->get_name(), "Sem nome"),
      "Peso"          => $this->sanitize_value($merchandise["data"]->get_weight(), 1),
      "Preco"         => $this->sanitize_value($merchandise["data"]->get_price(), 1),
      "Quantidade"    => $this->sanitize_value($merchandise["quantity"], 1),
      "TipoCaixa"     => true,
      "TipoEnvelope"  => false
    ];
  }

  /**
    * Function that checks the settings.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @return    boolean                               Inform if the settings are valid.
    */
  final private function is_settings_valid () : bool {
    return (
      $this->sanitize_value($this->settings["api_key"], null) !== null
      &&
      $this->sanitize_value($this->settings["store_id"], null) !== null
      &&
      $this->sanitize_value($this->settings["store_location"], null) !== null
    );
  }

  /**
    * Function that calculates shipping.
    * 
    * @since     1.0.0
    * 
    * @final
    * 
    * @param     array                   $package      Collection of package properties.
    * 
    * @return    void                                  Nothing to return.
    */
  final public function calculate_shipping ($package = []) : void {
    if ($this->is_settings_valid()) {
      $this->get_place_suggestions(
        $package["destination"]["postcode"],
        function ($place_suggestions) use ($package) {
          $this->get_place_details(
            $place_suggestions,
            function ($place_details) use ($package) {
              $this->get_directions(
                $place_details,
                function ($directions) use ($package, $place_details) {
                  $this->delivery_request_create(
                    $place_details,
                    $directions,
                    function ($delivery_request_id) use ($package) {
                      $this->merchandises_create(
                        $package,
                        $delivery_request_id,
                        function () use ($delivery_request_id) {
                          $this->get_rates(
                            $delivery_request_id,
                            function ($rates) {
                              foreach ($rates as $rate) {
                                $this->add_rate($rate);
                              }
                            }
                          );
                        }
                      );
                    }
                  );
                }
              );
            }
          );
        }
      );
    }
  }
}

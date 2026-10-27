<?php
/**
 * Panel de administración: configuración, estado del índice y ayuda.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DGES_Docs_Settings {

    const GROUP = 'dges_documentos_general';

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register' ) );
    }

    public static function menu() {
        add_menu_page(
            __( 'Documentos DGES', 'dges-documentos' ),
            __( 'Documentos DGES', 'dges-documentos' ),
            'manage_options',
            'dges-documentos',
            array( __CLASS__, 'render' ),
            'dashicons-media-document',
            26
        );
    }

    public static function register() {
        register_setting(
            self::GROUP,
            DGES_Docs_Source::OPTION_URL,
            array(
                'sanitize_callback' => 'esc_url_raw',
                'default'           => DGES_Docs_Source::default_url(),
            )
        );

        register_setting(
            self::GROUP,
            DGES_Docs_Repository::MODE_OPTION,
            array(
                'sanitize_callback' => array( __CLASS__, 'sanitize_mode' ),
                'default'           => 'index',
            )
        );

        register_setting(
            self::GROUP,
            DGES_Docs_Source::OPTION_FILES_BASE,
            array(
                'sanitize_callback' => 'esc_url_raw',
                'default'           => '',
            )
        );

        register_setting(
            self::GROUP,
            'dges_documentos_per_page',
            array(
                'sanitize_callback' => array( __CLASS__, 'sanitize_per_page' ),
                'default'           => 12,
            )
        );

        register_setting(
            self::GROUP,
            'dges_documentos_limit_destacados',
            array(
                'sanitize_callback' => array( __CLASS__, 'sanitize_featured_limit' ),
                'default'           => 4,
            )
        );

        register_setting(
            self::GROUP,
            DGES_Docs_Router::OPTION_DETAIL_PAGE,
            array(
                'sanitize_callback' => array( __CLASS__, 'sanitize_page_id' ),
                'default'           => 0,
            )
        );

        register_setting(
            self::GROUP,
            DGES_Docs_Router::OPTION_LIST_PAGE,
            array(
                'sanitize_callback' => array( __CLASS__, 'sanitize_page_id' ),
                'default'           => 0,
            )
        );

        add_action( 'update_option_' . DGES_Docs_Router::OPTION_DETAIL_PAGE, array( 'DGES_Docs_Router', 'flush_rewrite' ) );
        add_action( 'update_option_' . DGES_Docs_Source::OPTION_URL, array( __CLASS__, 'on_change_clear' ) );
        add_action( 'update_option_' . DGES_Docs_Source::OPTION_FILES_BASE, array( __CLASS__, 'on_change_clear' ) );
        add_action( 'update_option_' . DGES_Docs_Repository::MODE_OPTION, array( __CLASS__, 'on_change_clear' ) );
    }

    public static function sanitize_mode( $value ) {
        return in_array( $value, array( 'index', 'api' ), true ) ? $value : 'index';
    }

    public static function sanitize_per_page( $value ) {
        $value = absint( $value );

        if ( $value < 1 ) {
            return 12;
        }

        return min( DGES_Docs_Query::MAX_PER_PAGE, $value );
    }

    public static function sanitize_featured_limit( $value ) {
        $value = absint( $value );

        if ( $value < 1 ) {
            return 4;
        }

        return min( 20, $value );
    }

    public static function sanitize_page_id( $value ) {
        $value = absint( $value );

        return $value > 0 && 'page' === get_post_type( $value ) ? $value : 0;
    }

    public static function on_change_clear() {
        DGES_Docs_Repository::clear_all();
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $notice = self::handle_actions();
        $tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
        $tabs   = array(
            'general' => __( 'Configuración', 'dges-documentos' ),
            'cache'   => __( 'Índice y caché', 'dges-documentos' ),
            'help'    => __( 'Ayuda', 'dges-documentos' ),
        );

        if ( ! isset( $tabs[ $tab ] ) ) {
            $tab = 'general';
        }

        ?>
        <div class="wrap dges-admin">
            <h1><?php esc_html_e( 'Documentos DGES', 'dges-documentos' ); ?></h1>

            <?php if ( $notice ) : ?>
                <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
                    <p><?php echo esc_html( $notice['message'] ); ?></p>
                </div>
            <?php endif; ?>

            <?php settings_errors(); ?>

            <nav class="nav-tab-wrapper">
                <?php foreach ( $tabs as $key => $label ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'dges-documentos', 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>"
                       class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if ( 'general' === $tab ) : ?>
                <?php self::render_general(); ?>
            <?php elseif ( 'cache' === $tab ) : ?>
                <?php self::render_cache(); ?>
            <?php else : ?>
                <?php self::render_help(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function handle_actions() {
        if ( ! isset( $_POST['dges_docs_action'] ) ) {
            return null;
        }

        check_admin_referer( 'dges_docs_action' );

        $action = sanitize_key( wp_unslash( $_POST['dges_docs_action'] ) );

        if ( 'refresh' === $action ) {
            $result = DGES_Docs_Repository::refresh_now();

            if ( is_wp_error( $result ) ) {
                return array(
                    'type'    => 'error',
                    'message' => $result->get_error_message(),
                );
            }

            return array(
                'type'    => 'success',
                'message' => __( 'Índice actualizado correctamente.', 'dges-documentos' ),
            );
        }

        if ( 'clear' === $action ) {
            DGES_Docs_Repository::clear_all();

            return array(
                'type'    => 'success',
                'message' => __( 'Caché vaciada. El índice se vuelve a generar en la próxima consulta.', 'dges-documentos' ),
            );
        }

        return null;
    }

    private static function render_general() {
        $mode        = get_option( DGES_Docs_Repository::MODE_OPTION, 'index' );
        $pages       = get_pages( array( 'sort_column' => 'post_title', 'sort_order' => 'ASC' ) );
        $detail_page = (int) get_option( DGES_Docs_Router::OPTION_DETAIL_PAGE, 0 );
        $list_page   = (int) get_option( DGES_Docs_Router::OPTION_LIST_PAGE, 0 );

        if ( ! is_array( $pages ) ) {
            $pages = array();
        }
        ?>
        <form method="post" action="options.php" class="dges-admin-form">
            <?php settings_fields( self::GROUP ); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="dges-source-url"><?php esc_html_e( 'URL del listado de documentos', 'dges-documentos' ); ?></label>
                    </th>
                    <td>
                        <input type="url" class="regular-text code" id="dges-source-url"
                               name="<?php echo esc_attr( DGES_Docs_Source::OPTION_URL ); ?>"
                               value="<?php echo esc_attr( DGES_Docs_Source::get_source_url() ); ?>" required>
                        <p class="description"><?php esc_html_e( 'Endpoint que devuelve { documentos: [...] }. El detalle se pide con ?iddocumento={id}.', 'dges-documentos' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dges-search-mode"><?php esc_html_e( 'Modo de búsqueda', 'dges-documentos' ); ?></label>
                    </th>
                    <td>
                        <select id="dges-search-mode" name="<?php echo esc_attr( DGES_Docs_Repository::MODE_OPTION ); ?>">
                            <option value="index" <?php selected( $mode, 'index' ); ?>><?php esc_html_e( 'Índice local (recomendado por ahora)', 'dges-documentos' ); ?></option>
                            <option value="api" <?php selected( $mode, 'api' ); ?>><?php esc_html_e( 'Búsqueda en la API (cuando el back la implemente)', 'dges-documentos' ); ?></option>
                        </select>
                        <p class="description">
                            <?php esc_html_e( 'Índice local: baja el listado y filtra en WordPress. API: envía txtbusqueda, categoria, limit y offset al backend. No actives API hasta que el back confirme esos parámetros.', 'dges-documentos' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dges-files-base"><?php esc_html_e( 'Base pública de archivos (opcional)', 'dges-documentos' ); ?></label>
                    </th>
                    <td>
                        <input type="url" class="regular-text code" id="dges-files-base"
                               name="<?php echo esc_attr( DGES_Docs_Source::OPTION_FILES_BASE ); ?>"
                               value="<?php echo esc_attr( DGES_Docs_Source::get_files_base() ); ?>"
                               placeholder="https://…/bd_dges/">
                        <p class="description"><?php esc_html_e( 'Solo si la API no manda url por archivo. Se concatena con carpeta/nombre_archivo. Si la API manda url, se usa esa.', 'dges-documentos' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dges-detail-page"><?php esc_html_e( 'Página de detalle', 'dges-documentos' ); ?></label>
                    </th>
                    <td>
                        <select id="dges-detail-page" name="<?php echo esc_attr( DGES_Docs_Router::OPTION_DETAIL_PAGE ); ?>">
                            <option value="0" <?php selected( $detail_page, 0 ); ?>><?php esc_html_e( '— Sin asignar —', 'dges-documentos' ); ?></option>
                            <?php foreach ( $pages as $page ) : ?>
                                <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $detail_page, $page->ID ); ?>>
                                    <?php echo esc_html( $page->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Debe contener [dges_documento_detalle]. Si no existe, el plugin crea una página "Documento DGES" automáticamente.', 'dges-documentos' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dges-list-page"><?php esc_html_e( 'Página del listado', 'dges-documentos' ); ?></label>
                    </th>
                    <td>
                        <select id="dges-list-page" name="<?php echo esc_attr( DGES_Docs_Router::OPTION_LIST_PAGE ); ?>">
                            <option value="0" <?php selected( $list_page, 0 ); ?>><?php esc_html_e( '— Sin asignar —', 'dges-documentos' ); ?></option>
                            <?php foreach ( $pages as $page ) : ?>
                                <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $list_page, $page->ID ); ?>>
                                    <?php echo esc_html( $page->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Se usa para el breadcrumb y el botón "Volver a documentos". Si no la asignás, se detecta la página que contiene [dges_documentos].', 'dges-documentos' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dges-per-page"><?php esc_html_e( 'Documentos por página', 'dges-documentos' ); ?></label>
                    </th>
                    <td>
                        <input type="number" class="small-text" id="dges-per-page" min="1" max="<?php echo esc_attr( DGES_Docs_Query::MAX_PER_PAGE ); ?>"
                               name="dges_documentos_per_page"
                               value="<?php echo esc_attr( get_option( 'dges_documentos_per_page', 12 ) ); ?>">
                        <p class="description"><?php echo esc_html( sprintf( __( 'Máximo %d.', 'dges-documentos' ), DGES_Docs_Query::MAX_PER_PAGE ) ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dges-featured-limit"><?php esc_html_e( 'Cantidad de destacados', 'dges-documentos' ); ?></label>
                    </th>
                    <td>
                        <input type="number" class="small-text" id="dges-featured-limit" min="1" max="20"
                               name="dges_documentos_limit_destacados"
                               value="<?php echo esc_attr( get_option( 'dges_documentos_limit_destacados', 4 ) ); ?>">
                    </td>
                </tr>
            </table>

            <?php submit_button( __( 'Guardar cambios', 'dges-documentos' ) ); ?>
        </form>
        <?php
    }

    private static function render_cache() {
        $status = DGES_Docs_Repository::status();
        $is_api = 'api' === $status['mode'];
        $mode   = $is_api ? __( 'API', 'dges-documentos' ) : __( 'Índice local', 'dges-documentos' );

        $last_sync = $status['last_sync'] > 0
            ? sprintf(
                /* translators: 1: human time diff, 2: date */
                __( 'hace %1$s (%2$s)', 'dges-documentos' ),
                human_time_diff( $status['last_sync'], time() ),
                date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $status['last_sync'] )
            )
            : __( 'nunca', 'dges-documentos' );
        ?>
        <h2><?php esc_html_e( 'Estado del índice', 'dges-documentos' ); ?></h2>

        <table class="widefat striped dges-status-table" style="max-width: 760px;">
            <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Modo', 'dges-documentos' ); ?></th>
                    <td><?php echo esc_html( $mode ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Documentos en el índice', 'dges-documentos' ); ?></th>
                    <td><?php echo $is_api ? '—' : esc_html( number_format_i18n( $status['count'] ) ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Última sincronización', 'dges-documentos' ); ?></th>
                    <td><?php echo $is_api ? '—' : esc_html( $last_sync ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Estado', 'dges-documentos' ); ?></th>
                    <td>
                        <?php if ( $is_api ) : ?>
                            <span><?php esc_html_e( 'Modo API: cada búsqueda se resuelve contra el backend.', 'dges-documentos' ); ?></span>
                        <?php elseif ( $status['is_stale'] ) : ?>
                            <span class="dges-status-warn"><?php esc_html_e( 'Desactualizado: se sirve el índice anterior mientras se refresca.', 'dges-documentos' ); ?></span>
                        <?php elseif ( $status['count'] > 0 ) : ?>
                            <span class="dges-status-ok"><?php esc_html_e( 'Actualizado.', 'dges-documentos' ); ?></span>
                        <?php else : ?>
                            <span class="dges-status-warn"><?php esc_html_e( 'Sin datos. Actualizá el índice.', 'dges-documentos' ); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ( '' !== $status['last_error'] ) : ?>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Último error', 'dges-documentos' ); ?></th>
                        <td><code><?php echo esc_html( $status['last_error'] ); ?></code></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="dges-admin-actions">
            <?php if ( ! $is_api ) : ?>
                <form method="post">
                    <?php wp_nonce_field( 'dges_docs_action' ); ?>
                    <input type="hidden" name="dges_docs_action" value="refresh">
                    <?php submit_button( __( 'Actualizar índice ahora', 'dges-documentos' ), 'primary', 'submit', false ); ?>
                </form>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field( 'dges_docs_action' ); ?>
                <input type="hidden" name="dges_docs_action" value="clear">
                <?php submit_button( __( 'Vaciar caché', 'dges-documentos' ), 'secondary', 'submit', false ); ?>
            </form>
        </div>

        <p class="description">
            <?php esc_html_e( 'El índice se refresca solo cada hora. Si la API falla, se sigue mostrando el índice anterior.', 'dges-documentos' ); ?>
        </p>
        <?php
    }

    private static function render_help() {
        ?>
        <h2><?php esc_html_e( 'Shortcodes', 'dges-documentos' ); ?></h2>
        <ul class="dges-help-list">
            <li><code>[dges_documentos]</code> — <?php esc_html_e( 'Buscador con filtro por categoría y paginación.', 'dges-documentos' ); ?></li>
            <li><code>[dges_documentos_destacados]</code> — <?php esc_html_e( 'Tarjetas de los documentos marcados como destacados.', 'dges-documentos' ); ?></li>
            <li><code>[dges_documento id="116"]</code> — <?php esc_html_e( 'Card breve de un documento: insignia, título, una línea de descripción y botón de acción. Atributos: variant="compact|horizontal", link="detail|file", show_description="0", show_meta="0", file="idarchivo".', 'dges-documentos' ); ?></li>
            <li><code>[dges_documento_detalle]</code> — <?php esc_html_e( 'Contenido de la página de detalle. La ruta /documentos/documento/{id}/ se genera sola; la página se crea automáticamente y se puede cambiar en Configuración.', 'dges-documentos' ); ?></li>
        </ul>
        <p class="description"><?php esc_html_e( 'El ID de cada documento se ve en el listado del admin o en la URL del detalle. Si la API no manda url por archivo, configurá la base pública en Configuración.', 'dges-documentos' ); ?></p>

        <h2><?php esc_html_e( 'Búsqueda y filtros', 'dges-documentos' ); ?></h2>
        <p><?php esc_html_e( 'La barra busca en título, descripción y número. El select filtra por categoría. No hay filtros por año, tipo, vigencia ni número: esos datos se muestran como metadata.', 'dges-documentos' ); ?></p>

        <h2><?php esc_html_e( 'Formato esperado de la API', 'dges-documentos' ); ?></h2>
        <p><?php esc_html_e( 'Listado:', 'dges-documentos' ); ?></p>
        <pre class="dges-code"><code>{
  "documentos": [
    {
      "id": "116",
      "nombre": "Resolución Ministerial 116-2026",
      "observacion": "Referido a la aprobación de propuestas de postítulos.",
      "numero": "116",
      "anio": "2026",
      "vigente": "Si",
      "destacado": "No",
      "categorias": [
        { "idcategoria": "1", "nombre": "Normativas DGES" }
      ]
    }
  ]
}</code></pre>

        <p><?php esc_html_e( 'Detalle (?iddocumento={id}): agrega archivos y documentos_relacionados.', 'dges-documentos' ); ?></p>
        <pre class="dges-code"><code>{
  "id": "116",
  "nombre": "…",
  "archivos": [
    {
      "idarchivo": "45",
      "nombre": "RM 116-2026",
      "descripcion": "Propuestas de postítulos",
      "tipo": "PDF",
      "carpeta": "archivos/2026/10/23",
      "nombre_archivo": "2026_10_23.pdf",
      "tamanio": "19801962",
      "url": "https://…/2026_10_23.pdf"
    }
  ],
  "documentos_relacionados": [
    {
      "id": "4",
      "tipo": "Complementa",
      "nombre": "Resolución 337-2025",
      "numero": "337",
      "anio": "2025",
      "vigente": "Si",
      "destacado": "No"
    }
  ]
}</code></pre>

        <p class="description">
            <?php esc_html_e( 'Si el detalle no existe o la API devuelve texto plano, el plugin lo trata como 404. Pedile al back que devuelva JSON con status 404.', 'dges-documentos' ); ?>
        </p>
        <?php
    }
}

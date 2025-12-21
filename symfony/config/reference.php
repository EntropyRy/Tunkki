<?php

// This file is auto-generated and is for apps only. Bundles SHOULD NOT rely on its content.

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

/**
 * This class provides array-shapes for configuring the services and bundles of an application.
 *
 * Services declared with the config() method below are autowired and autoconfigured by default.
 *
 * This is for apps only. Bundles SHOULD NOT use it.
 *
 * Example:
 *
 *     ```php
 *     // config/services.php
 *     namespace Symfony\Component\DependencyInjection\Loader\Configurator;
 *
 *     return App::config([
 *         'services' => [
 *             'App\\' => [
 *                 'resource' => '../src/',
 *             ],
 *         ],
 *     ]);
 *     ```
 *
 * @psalm-type ImportsConfig = list<string|array{
 *     resource: string,
 *     type?: string|null,
 *     ignore_errors?: bool,
 * }>
 * @psalm-type ParametersConfig = array<string, scalar|\UnitEnum|array<scalar|\UnitEnum|array<mixed>|null>|null>
 * @psalm-type ArgumentsType = list<mixed>|array<string, mixed>
 * @psalm-type CallType = array<string, ArgumentsType>|array{0:string, 1?:ArgumentsType, 2?:bool}|array{method:string, arguments?:ArgumentsType, returns_clone?:bool}
 * @psalm-type TagsType = list<string|array<string, array<string, mixed>>> // arrays inside the list must have only one element, with the tag name as the key
 * @psalm-type CallbackType = string|array{0:string|ReferenceConfigurator,1:string}|\Closure|ReferenceConfigurator|ExpressionConfigurator
 * @psalm-type DeprecationType = array{package: string, version: string, message?: string}
 * @psalm-type DefaultsType = array{
 *     public?: bool,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     autowire?: bool,
 *     autoconfigure?: bool,
 *     bind?: array<string, mixed>,
 * }
 * @psalm-type InstanceofType = array{
 *     shared?: bool,
 *     lazy?: bool|string,
 *     public?: bool,
 *     properties?: array<string, mixed>,
 *     configurator?: CallbackType,
 *     calls?: list<CallType>,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     autowire?: bool,
 *     bind?: array<string, mixed>,
 *     constructor?: string,
 * }
 * @psalm-type DefinitionType = array{
 *     class?: string,
 *     file?: string,
 *     parent?: string,
 *     shared?: bool,
 *     synthetic?: bool,
 *     lazy?: bool|string,
 *     public?: bool,
 *     abstract?: bool,
 *     deprecated?: DeprecationType,
 *     factory?: CallbackType,
 *     configurator?: CallbackType,
 *     arguments?: ArgumentsType,
 *     properties?: array<string, mixed>,
 *     calls?: list<CallType>,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     decorates?: string,
 *     decoration_inner_name?: string,
 *     decoration_priority?: int,
 *     decoration_on_invalid?: 'exception'|'ignore'|null,
 *     autowire?: bool,
 *     autoconfigure?: bool,
 *     bind?: array<string, mixed>,
 *     constructor?: string,
 *     from_callable?: CallbackType,
 * }
 * @psalm-type AliasType = string|array{
 *     alias: string,
 *     public?: bool,
 *     deprecated?: DeprecationType,
 * }
 * @psalm-type PrototypeType = array{
 *     resource: string,
 *     namespace?: string,
 *     exclude?: string|list<string>,
 *     parent?: string,
 *     shared?: bool,
 *     lazy?: bool|string,
 *     public?: bool,
 *     abstract?: bool,
 *     deprecated?: DeprecationType,
 *     factory?: CallbackType,
 *     arguments?: ArgumentsType,
 *     properties?: array<string, mixed>,
 *     configurator?: CallbackType,
 *     calls?: list<CallType>,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     autowire?: bool,
 *     autoconfigure?: bool,
 *     bind?: array<string, mixed>,
 *     constructor?: string,
 * }
 * @psalm-type StackType = array{
 *     stack: list<DefinitionType|AliasType|PrototypeType|array<class-string, ArgumentsType|null>>,
 *     public?: bool,
 *     deprecated?: DeprecationType,
 * }
 * @psalm-type ServicesConfig = array{
 *     _defaults?: DefaultsType,
 *     _instanceof?: InstanceofType,
 *     ...<string, DefinitionType|AliasType|PrototypeType|StackType|ArgumentsType|null>
 * }
 * @psalm-type ExtensionType = array<string, mixed>
 * @psalm-type DoctrineConfig = array{
 *     dbal?: array{
 *         default_connection?: scalar|null,
 *         types?: array<string, string|array{ // Default: []
 *             class: scalar|null,
 *         }>,
 *         driver_schemes?: array<string, scalar|null>,
 *         connections?: array<string, array{ // Default: []
 *             url?: scalar|null, // A URL with connection information; any parameter value parsed from this string will override explicitly set parameters
 *             dbname?: scalar|null,
 *             host?: scalar|null, // Defaults to "localhost" at runtime.
 *             port?: scalar|null, // Defaults to null at runtime.
 *             user?: scalar|null, // Defaults to "root" at runtime.
 *             password?: scalar|null, // Defaults to null at runtime.
 *             dbname_suffix?: scalar|null, // Adds the given suffix to the configured database name, this option has no effects for the SQLite platform
 *             application_name?: scalar|null,
 *             charset?: scalar|null,
 *             path?: scalar|null,
 *             memory?: bool,
 *             unix_socket?: scalar|null, // The unix socket to use for MySQL
 *             persistent?: bool, // True to use as persistent connection for the ibm_db2 driver
 *             protocol?: scalar|null, // The protocol to use for the ibm_db2 driver (default to TCPIP if omitted)
 *             service?: bool, // True to use SERVICE_NAME as connection parameter instead of SID for Oracle
 *             servicename?: scalar|null, // Overrules dbname parameter if given and used as SERVICE_NAME or SID connection parameter for Oracle depending on the service parameter.
 *             sessionMode?: scalar|null, // The session mode to use for the oci8 driver
 *             server?: scalar|null, // The name of a running database server to connect to for SQL Anywhere.
 *             default_dbname?: scalar|null, // Override the default database (postgres) to connect to for PostgreSQL connexion.
 *             sslmode?: scalar|null, // Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL.
 *             sslrootcert?: scalar|null, // The name of a file containing SSL certificate authority (CA) certificate(s). If the file exists, the server's certificate will be verified to be signed by one of these authorities.
 *             sslcert?: scalar|null, // The path to the SSL client certificate file for PostgreSQL.
 *             sslkey?: scalar|null, // The path to the SSL client key file for PostgreSQL.
 *             sslcrl?: scalar|null, // The file name of the SSL certificate revocation list for PostgreSQL.
 *             pooled?: bool, // True to use a pooled server with the oci8/pdo_oracle driver
 *             MultipleActiveResultSets?: bool, // Configuring MultipleActiveResultSets for the pdo_sqlsrv driver
 *             instancename?: scalar|null, // Optional parameter, complete whether to add the INSTANCE_NAME parameter in the connection. It is generally used to connect to an Oracle RAC server to select the name of a particular instance.
 *             connectstring?: scalar|null, // Complete Easy Connect connection descriptor, see https://docs.oracle.com/database/121/NETAG/naming.htm.When using this option, you will still need to provide the user and password parameters, but the other parameters will no longer be used. Note that when using this parameter, the getHost and getPort methods from Doctrine\DBAL\Connection will no longer function as expected.
 *             driver?: scalar|null, // Default: "pdo_mysql"
 *             auto_commit?: bool,
 *             schema_filter?: scalar|null,
 *             logging?: bool, // Default: true
 *             profiling?: bool, // Default: true
 *             profiling_collect_backtrace?: bool, // Enables collecting backtraces when profiling is enabled // Default: false
 *             profiling_collect_schema_errors?: bool, // Enables collecting schema errors when profiling is enabled // Default: true
 *             server_version?: scalar|null,
 *             idle_connection_ttl?: int, // Default: 600
 *             driver_class?: scalar|null,
 *             wrapper_class?: scalar|null,
 *             keep_replica?: bool,
 *             options?: array<string, mixed>,
 *             mapping_types?: array<string, scalar|null>,
 *             default_table_options?: array<string, scalar|null>,
 *             schema_manager_factory?: scalar|null, // Default: "doctrine.dbal.default_schema_manager_factory"
 *             result_cache?: scalar|null,
 *             replicas?: array<string, array{ // Default: []
 *                 url?: scalar|null, // A URL with connection information; any parameter value parsed from this string will override explicitly set parameters
 *                 dbname?: scalar|null,
 *                 host?: scalar|null, // Defaults to "localhost" at runtime.
 *                 port?: scalar|null, // Defaults to null at runtime.
 *                 user?: scalar|null, // Defaults to "root" at runtime.
 *                 password?: scalar|null, // Defaults to null at runtime.
 *                 dbname_suffix?: scalar|null, // Adds the given suffix to the configured database name, this option has no effects for the SQLite platform
 *                 application_name?: scalar|null,
 *                 charset?: scalar|null,
 *                 path?: scalar|null,
 *                 memory?: bool,
 *                 unix_socket?: scalar|null, // The unix socket to use for MySQL
 *                 persistent?: bool, // True to use as persistent connection for the ibm_db2 driver
 *                 protocol?: scalar|null, // The protocol to use for the ibm_db2 driver (default to TCPIP if omitted)
 *                 service?: bool, // True to use SERVICE_NAME as connection parameter instead of SID for Oracle
 *                 servicename?: scalar|null, // Overrules dbname parameter if given and used as SERVICE_NAME or SID connection parameter for Oracle depending on the service parameter.
 *                 sessionMode?: scalar|null, // The session mode to use for the oci8 driver
 *                 server?: scalar|null, // The name of a running database server to connect to for SQL Anywhere.
 *                 default_dbname?: scalar|null, // Override the default database (postgres) to connect to for PostgreSQL connexion.
 *                 sslmode?: scalar|null, // Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL.
 *                 sslrootcert?: scalar|null, // The name of a file containing SSL certificate authority (CA) certificate(s). If the file exists, the server's certificate will be verified to be signed by one of these authorities.
 *                 sslcert?: scalar|null, // The path to the SSL client certificate file for PostgreSQL.
 *                 sslkey?: scalar|null, // The path to the SSL client key file for PostgreSQL.
 *                 sslcrl?: scalar|null, // The file name of the SSL certificate revocation list for PostgreSQL.
 *                 pooled?: bool, // True to use a pooled server with the oci8/pdo_oracle driver
 *                 MultipleActiveResultSets?: bool, // Configuring MultipleActiveResultSets for the pdo_sqlsrv driver
 *                 instancename?: scalar|null, // Optional parameter, complete whether to add the INSTANCE_NAME parameter in the connection. It is generally used to connect to an Oracle RAC server to select the name of a particular instance.
 *                 connectstring?: scalar|null, // Complete Easy Connect connection descriptor, see https://docs.oracle.com/database/121/NETAG/naming.htm.When using this option, you will still need to provide the user and password parameters, but the other parameters will no longer be used. Note that when using this parameter, the getHost and getPort methods from Doctrine\DBAL\Connection will no longer function as expected.
 *             }>,
 *         }>,
 *     },
 *     orm?: array{
 *         default_entity_manager?: scalar|null,
 *         enable_native_lazy_objects?: bool, // Deprecated: The "enable_native_lazy_objects" option is deprecated and will be removed in DoctrineBundle 4.0, as native lazy objects are now always enabled. // Default: true
 *         controller_resolver?: bool|array{
 *             enabled?: bool, // Default: true
 *             auto_mapping?: bool, // Deprecated: The "auto_mapping" option is deprecated and will be removed in DoctrineBundle 4.0, as it only accepts `false` since 3.0. // Set to true to enable using route placeholders as lookup criteria when the primary key doesn't match the argument name // Default: false
 *             evict_cache?: bool, // Set to true to fetch the entity from the database instead of using the cache, if any // Default: false
 *         },
 *         entity_managers?: array<string, array{ // Default: []
 *             query_cache_driver?: string|array{
 *                 type?: scalar|null, // Default: null
 *                 id?: scalar|null,
 *                 pool?: scalar|null,
 *             },
 *             metadata_cache_driver?: string|array{
 *                 type?: scalar|null, // Default: null
 *                 id?: scalar|null,
 *                 pool?: scalar|null,
 *             },
 *             result_cache_driver?: string|array{
 *                 type?: scalar|null, // Default: null
 *                 id?: scalar|null,
 *                 pool?: scalar|null,
 *             },
 *             entity_listeners?: array{
 *                 entities?: array<string, array{ // Default: []
 *                     listeners?: array<string, array{ // Default: []
 *                         events?: list<array{ // Default: []
 *                             type?: scalar|null,
 *                             method?: scalar|null, // Default: null
 *                         }>,
 *                     }>,
 *                 }>,
 *             },
 *             connection?: scalar|null,
 *             class_metadata_factory_name?: scalar|null, // Default: "Doctrine\\ORM\\Mapping\\ClassMetadataFactory"
 *             default_repository_class?: scalar|null, // Default: "Doctrine\\ORM\\EntityRepository"
 *             auto_mapping?: scalar|null, // Default: false
 *             naming_strategy?: scalar|null, // Default: "doctrine.orm.naming_strategy.default"
 *             quote_strategy?: scalar|null, // Default: "doctrine.orm.quote_strategy.default"
 *             typed_field_mapper?: scalar|null, // Default: "doctrine.orm.typed_field_mapper.default"
 *             entity_listener_resolver?: scalar|null, // Default: null
 *             fetch_mode_subselect_batch_size?: scalar|null,
 *             repository_factory?: scalar|null, // Default: "doctrine.orm.container_repository_factory"
 *             schema_ignore_classes?: list<scalar|null>,
 *             validate_xml_mapping?: bool, // Set to "true" to opt-in to the new mapping driver mode that was added in Doctrine ORM 2.14 and will be mandatory in ORM 3.0. See https://github.com/doctrine/orm/pull/6728. // Default: false
 *             second_level_cache?: array{
 *                 region_cache_driver?: string|array{
 *                     type?: scalar|null, // Default: null
 *                     id?: scalar|null,
 *                     pool?: scalar|null,
 *                 },
 *                 region_lock_lifetime?: scalar|null, // Default: 60
 *                 log_enabled?: bool, // Default: true
 *                 region_lifetime?: scalar|null, // Default: 3600
 *                 enabled?: bool, // Default: true
 *                 factory?: scalar|null,
 *                 regions?: array<string, array{ // Default: []
 *                     cache_driver?: string|array{
 *                         type?: scalar|null, // Default: null
 *                         id?: scalar|null,
 *                         pool?: scalar|null,
 *                     },
 *                     lock_path?: scalar|null, // Default: "%kernel.cache_dir%/doctrine/orm/slc/filelock"
 *                     lock_lifetime?: scalar|null, // Default: 60
 *                     type?: scalar|null, // Default: "default"
 *                     lifetime?: scalar|null, // Default: 0
 *                     service?: scalar|null,
 *                     name?: scalar|null,
 *                 }>,
 *                 loggers?: array<string, array{ // Default: []
 *                     name?: scalar|null,
 *                     service?: scalar|null,
 *                 }>,
 *             },
 *             hydrators?: array<string, scalar|null>,
 *             mappings?: array<string, bool|string|array{ // Default: []
 *                 mapping?: scalar|null, // Default: true
 *                 type?: scalar|null,
 *                 dir?: scalar|null,
 *                 alias?: scalar|null,
 *                 prefix?: scalar|null,
 *                 is_bundle?: bool,
 *             }>,
 *             dql?: array{
 *                 string_functions?: array<string, scalar|null>,
 *                 numeric_functions?: array<string, scalar|null>,
 *                 datetime_functions?: array<string, scalar|null>,
 *             },
 *             filters?: array<string, string|array{ // Default: []
 *                 class: scalar|null,
 *                 enabled?: bool, // Default: false
 *                 parameters?: array<string, mixed>,
 *             }>,
 *             identity_generation_preferences?: array<string, scalar|null>,
 *         }>,
 *         resolve_target_entities?: array<string, scalar|null>,
 *     },
 * }
 * @psalm-type FosCkEditorConfig = array{
 *     enable?: bool, // Default: true
 *     async?: bool, // Default: false
 *     auto_inline?: bool, // Default: true
 *     inline?: bool, // Default: false
 *     autoload?: bool, // Default: true
 *     jquery?: bool, // Default: false
 *     require_js?: bool, // Default: false
 *     input_sync?: bool, // Default: false
 *     base_path?: scalar|null, // Default: "bundles/fosckeditor/"
 *     js_path?: scalar|null, // Default: "bundles/fosckeditor/ckeditor.js"
 *     jquery_path?: scalar|null, // Default: "bundles/fosckeditor/adapters/jquery.js"
 *     default_config?: scalar|null, // Default: null
 *     configs?: array<string, array<string, mixed>>,
 *     plugins?: array<string, array{ // Default: []
 *         path?: scalar|null,
 *         filename?: scalar|null,
 *     }>,
 *     styles?: array<string, list<array{ // Default: []
 *             name?: scalar|null,
 *             type?: scalar|null,
 *             widget?: scalar|null,
 *             element?: mixed,
 *             styles?: array<string, scalar|null>,
 *             attributes?: array<string, scalar|null>,
 *         }>>,
 *     templates?: array<string, array{ // Default: []
 *         imagesPath?: scalar|null,
 *         templates?: list<array{ // Default: []
 *             title?: scalar|null,
 *             image?: scalar|null,
 *             description?: scalar|null,
 *             html?: scalar|null,
 *             template?: scalar|null,
 *             template_parameters?: array<string, scalar|null>,
 *         }>,
 *     }>,
 *     filebrowsers?: array<string, scalar|null>,
 *     toolbars?: array{
 *         configs?: array<string, list<mixed>>,
 *         items?: array<string, list<mixed>>,
 *     },
 * }
 * @psalm-type KnpMenuConfig = array{
 *     providers?: array{
 *         builder_alias?: bool, // Default: true
 *     },
 *     twig?: array{
 *         template?: scalar|null, // Default: "@KnpMenu/menu.html.twig"
 *     },
 *     templating?: bool, // Default: false
 *     default_renderer?: scalar|null, // Default: "twig"
 * }
 * @psalm-type KnpuOauth2ClientConfig = array{
 *     http_client?: scalar|null, // Service id of HTTP client to use (must implement GuzzleHttp\ClientInterface) // Default: null
 *     http_client_options?: array{
 *         timeout?: int,
 *         proxy?: scalar|null,
 *         verify?: bool, // Use only with proxy option set
 *     },
 *     clients?: array<string, array<string, mixed>>,
 * }
 * @psalm-type FrameworkConfig = array{
 *     secret?: scalar|null,
 *     http_method_override?: bool, // Set true to enable support for the '_method' request parameter to determine the intended HTTP method on POST requests. // Default: false
 *     allowed_http_method_override?: list<string>|null,
 *     trust_x_sendfile_type_header?: scalar|null, // Set true to enable support for xsendfile in binary file responses. // Default: "%env(bool:default::SYMFONY_TRUST_X_SENDFILE_TYPE_HEADER)%"
 *     ide?: scalar|null, // Default: "%env(default::SYMFONY_IDE)%"
 *     test?: bool,
 *     default_locale?: scalar|null, // Default: "en"
 *     set_locale_from_accept_language?: bool, // Whether to use the Accept-Language HTTP header to set the Request locale (only when the "_locale" request attribute is not passed). // Default: false
 *     set_content_language_from_locale?: bool, // Whether to set the Content-Language HTTP header on the Response using the Request locale. // Default: false
 *     enabled_locales?: list<scalar|null>,
 *     trusted_hosts?: list<scalar|null>,
 *     trusted_proxies?: mixed, // Default: ["%env(default::SYMFONY_TRUSTED_PROXIES)%"]
 *     trusted_headers?: list<scalar|null>,
 *     error_controller?: scalar|null, // Default: "error_controller"
 *     handle_all_throwables?: bool, // HttpKernel will handle all kinds of \Throwable. // Default: true
 *     csrf_protection?: bool|array{
 *         enabled?: scalar|null, // Default: null
 *         stateless_token_ids?: list<scalar|null>,
 *         check_header?: scalar|null, // Whether to check the CSRF token in a header in addition to a cookie when using stateless protection. // Default: false
 *         cookie_name?: scalar|null, // The name of the cookie to use when using stateless protection. // Default: "csrf-token"
 *     },
 *     form?: bool|array{ // Form configuration
 *         enabled?: bool, // Default: true
 *         csrf_protection?: array{
 *             enabled?: scalar|null, // Default: null
 *             token_id?: scalar|null, // Default: null
 *             field_name?: scalar|null, // Default: "_token"
 *             field_attr?: array<string, scalar|null>,
 *         },
 *     },
 *     http_cache?: bool|array{ // HTTP cache configuration
 *         enabled?: bool, // Default: false
 *         debug?: bool, // Default: "%kernel.debug%"
 *         trace_level?: "none"|"short"|"full",
 *         trace_header?: scalar|null,
 *         default_ttl?: int,
 *         private_headers?: list<scalar|null>,
 *         skip_response_headers?: list<scalar|null>,
 *         allow_reload?: bool,
 *         allow_revalidate?: bool,
 *         stale_while_revalidate?: int,
 *         stale_if_error?: int,
 *         terminate_on_cache_hit?: bool,
 *     },
 *     esi?: bool|array{ // ESI configuration
 *         enabled?: bool, // Default: false
 *     },
 *     ssi?: bool|array{ // SSI configuration
 *         enabled?: bool, // Default: false
 *     },
 *     fragments?: bool|array{ // Fragments configuration
 *         enabled?: bool, // Default: false
 *         hinclude_default_template?: scalar|null, // Default: null
 *         path?: scalar|null, // Default: "/_fragment"
 *     },
 *     profiler?: bool|array{ // Profiler configuration
 *         enabled?: bool, // Default: false
 *         collect?: bool, // Default: true
 *         collect_parameter?: scalar|null, // The name of the parameter to use to enable or disable collection on a per request basis. // Default: null
 *         only_exceptions?: bool, // Default: false
 *         only_main_requests?: bool, // Default: false
 *         dsn?: scalar|null, // Default: "file:%kernel.cache_dir%/profiler"
 *         collect_serializer_data?: bool, // Enables the serializer data collector and profiler panel. // Default: false
 *     },
 *     workflows?: bool|array{
 *         enabled?: bool, // Default: false
 *         workflows?: array<string, array{ // Default: []
 *             audit_trail?: bool|array{
 *                 enabled?: bool, // Default: false
 *             },
 *             type?: "workflow"|"state_machine", // Default: "state_machine"
 *             marking_store?: array{
 *                 type?: "method",
 *                 property?: scalar|null,
 *                 service?: scalar|null,
 *             },
 *             supports?: list<scalar|null>,
 *             definition_validators?: list<scalar|null>,
 *             support_strategy?: scalar|null,
 *             initial_marking?: list<scalar|null>,
 *             events_to_dispatch?: list<string>|null,
 *             places?: list<array{ // Default: []
 *                 name: scalar|null,
 *                 metadata?: list<mixed>,
 *             }>,
 *             transitions: list<array{ // Default: []
 *                 name: string,
 *                 guard?: string, // An expression to block the transition.
 *                 from?: list<array{ // Default: []
 *                     place: string,
 *                     weight?: int, // Default: 1
 *                 }>,
 *                 to?: list<array{ // Default: []
 *                     place: string,
 *                     weight?: int, // Default: 1
 *                 }>,
 *                 weight?: int, // Default: 1
 *                 metadata?: list<mixed>,
 *             }>,
 *             metadata?: list<mixed>,
 *         }>,
 *     },
 *     router?: bool|array{ // Router configuration
 *         enabled?: bool, // Default: false
 *         resource: scalar|null,
 *         type?: scalar|null,
 *         cache_dir?: scalar|null, // Deprecated: Setting the "framework.router.cache_dir.cache_dir" configuration option is deprecated. It will be removed in version 8.0. // Default: "%kernel.build_dir%"
 *         default_uri?: scalar|null, // The default URI used to generate URLs in a non-HTTP context. // Default: null
 *         http_port?: scalar|null, // Default: 80
 *         https_port?: scalar|null, // Default: 443
 *         strict_requirements?: scalar|null, // set to true to throw an exception when a parameter does not match the requirements set to false to disable exceptions when a parameter does not match the requirements (and return null instead) set to null to disable parameter checks against requirements 'true' is the preferred configuration in development mode, while 'false' or 'null' might be preferred in production // Default: true
 *         utf8?: bool, // Default: true
 *     },
 *     session?: bool|array{ // Session configuration
 *         enabled?: bool, // Default: false
 *         storage_factory_id?: scalar|null, // Default: "session.storage.factory.native"
 *         handler_id?: scalar|null, // Defaults to using the native session handler, or to the native *file* session handler if "save_path" is not null.
 *         name?: scalar|null,
 *         cookie_lifetime?: scalar|null,
 *         cookie_path?: scalar|null,
 *         cookie_domain?: scalar|null,
 *         cookie_secure?: true|false|"auto", // Default: "auto"
 *         cookie_httponly?: bool, // Default: true
 *         cookie_samesite?: null|"lax"|"strict"|"none", // Default: "lax"
 *         use_cookies?: bool,
 *         gc_divisor?: scalar|null,
 *         gc_probability?: scalar|null,
 *         gc_maxlifetime?: scalar|null,
 *         save_path?: scalar|null, // Defaults to "%kernel.cache_dir%/sessions" if the "handler_id" option is not null.
 *         metadata_update_threshold?: int, // Seconds to wait between 2 session metadata updates. // Default: 0
 *         sid_length?: int, // Deprecated: Setting the "framework.session.sid_length.sid_length" configuration option is deprecated. It will be removed in version 8.0. No alternative is provided as PHP 8.4 has deprecated the related option.
 *         sid_bits_per_character?: int, // Deprecated: Setting the "framework.session.sid_bits_per_character.sid_bits_per_character" configuration option is deprecated. It will be removed in version 8.0. No alternative is provided as PHP 8.4 has deprecated the related option.
 *     },
 *     request?: bool|array{ // Request configuration
 *         enabled?: bool, // Default: false
 *         formats?: array<string, string|list<scalar|null>>,
 *     },
 *     assets?: bool|array{ // Assets configuration
 *         enabled?: bool, // Default: true
 *         strict_mode?: bool, // Throw an exception if an entry is missing from the manifest.json. // Default: false
 *         version_strategy?: scalar|null, // Default: null
 *         version?: scalar|null, // Default: null
 *         version_format?: scalar|null, // Default: "%%s?%%s"
 *         json_manifest_path?: scalar|null, // Default: null
 *         base_path?: scalar|null, // Default: ""
 *         base_urls?: list<scalar|null>,
 *         packages?: array<string, array{ // Default: []
 *             strict_mode?: bool, // Throw an exception if an entry is missing from the manifest.json. // Default: false
 *             version_strategy?: scalar|null, // Default: null
 *             version?: scalar|null,
 *             version_format?: scalar|null, // Default: null
 *             json_manifest_path?: scalar|null, // Default: null
 *             base_path?: scalar|null, // Default: ""
 *             base_urls?: list<scalar|null>,
 *         }>,
 *     },
 *     asset_mapper?: bool|array{ // Asset Mapper configuration
 *         enabled?: bool, // Default: true
 *         paths?: array<string, scalar|null>,
 *         excluded_patterns?: list<scalar|null>,
 *         exclude_dotfiles?: bool, // If true, any files starting with "." will be excluded from the asset mapper. // Default: true
 *         server?: bool, // If true, a "dev server" will return the assets from the public directory (true in "debug" mode only by default). // Default: true
 *         public_prefix?: scalar|null, // The public path where the assets will be written to (and served from when "server" is true). // Default: "/assets/"
 *         missing_import_mode?: "strict"|"warn"|"ignore", // Behavior if an asset cannot be found when imported from JavaScript or CSS files - e.g. "import './non-existent.js'". "strict" means an exception is thrown, "warn" means a warning is logged, "ignore" means the import is left as-is. // Default: "warn"
 *         extensions?: array<string, scalar|null>,
 *         importmap_path?: scalar|null, // The path of the importmap.php file. // Default: "%kernel.project_dir%/importmap.php"
 *         importmap_polyfill?: scalar|null, // The importmap name that will be used to load the polyfill. Set to false to disable. // Default: "es-module-shims"
 *         importmap_script_attributes?: array<string, scalar|null>,
 *         vendor_dir?: scalar|null, // The directory to store JavaScript vendors. // Default: "%kernel.project_dir%/assets/vendor"
 *         precompress?: bool|array{ // Precompress assets with Brotli, Zstandard and gzip.
 *             enabled?: bool, // Default: false
 *             formats?: list<scalar|null>,
 *             extensions?: list<scalar|null>,
 *         },
 *     },
 *     translator?: bool|array{ // Translator configuration
 *         enabled?: bool, // Default: true
 *         fallbacks?: list<scalar|null>,
 *         logging?: bool, // Default: false
 *         formatter?: scalar|null, // Default: "translator.formatter.default"
 *         cache_dir?: scalar|null, // Default: "%kernel.cache_dir%/translations"
 *         default_path?: scalar|null, // The default path used to load translations. // Default: "%kernel.project_dir%/translations"
 *         paths?: list<scalar|null>,
 *         pseudo_localization?: bool|array{
 *             enabled?: bool, // Default: false
 *             accents?: bool, // Default: true
 *             expansion_factor?: float, // Default: 1.0
 *             brackets?: bool, // Default: true
 *             parse_html?: bool, // Default: false
 *             localizable_html_attributes?: list<scalar|null>,
 *         },
 *         providers?: array<string, array{ // Default: []
 *             dsn?: scalar|null,
 *             domains?: list<scalar|null>,
 *             locales?: list<scalar|null>,
 *         }>,
 *         globals?: array<string, string|array{ // Default: []
 *             value?: mixed,
 *             message?: string,
 *             parameters?: array<string, scalar|null>,
 *             domain?: string,
 *         }>,
 *     },
 *     validation?: bool|array{ // Validation configuration
 *         enabled?: bool, // Default: true
 *         cache?: scalar|null, // Deprecated: Setting the "framework.validation.cache.cache" configuration option is deprecated. It will be removed in version 8.0.
 *         enable_attributes?: bool, // Default: true
 *         static_method?: list<scalar|null>,
 *         translation_domain?: scalar|null, // Default: "validators"
 *         email_validation_mode?: "html5"|"html5-allow-no-tld"|"strict"|"loose", // Default: "html5"
 *         mapping?: array{
 *             paths?: list<scalar|null>,
 *         },
 *         not_compromised_password?: bool|array{
 *             enabled?: bool, // When disabled, compromised passwords will be accepted as valid. // Default: true
 *             endpoint?: scalar|null, // API endpoint for the NotCompromisedPassword Validator. // Default: null
 *         },
 *         disable_translation?: bool, // Default: false
 *         auto_mapping?: array<string, array{ // Default: []
 *             services?: list<scalar|null>,
 *         }>,
 *     },
 *     annotations?: bool|array{
 *         enabled?: bool, // Default: false
 *     },
 *     serializer?: bool|array{ // Serializer configuration
 *         enabled?: bool, // Default: true
 *         enable_attributes?: bool, // Default: true
 *         name_converter?: scalar|null,
 *         circular_reference_handler?: scalar|null,
 *         max_depth_handler?: scalar|null,
 *         mapping?: array{
 *             paths?: list<scalar|null>,
 *         },
 *         default_context?: list<mixed>,
 *         named_serializers?: array<string, array{ // Default: []
 *             name_converter?: scalar|null,
 *             default_context?: list<mixed>,
 *             include_built_in_normalizers?: bool, // Whether to include the built-in normalizers // Default: true
 *             include_built_in_encoders?: bool, // Whether to include the built-in encoders // Default: true
 *         }>,
 *     },
 *     property_access?: bool|array{ // Property access configuration
 *         enabled?: bool, // Default: true
 *         magic_call?: bool, // Default: false
 *         magic_get?: bool, // Default: true
 *         magic_set?: bool, // Default: true
 *         throw_exception_on_invalid_index?: bool, // Default: false
 *         throw_exception_on_invalid_property_path?: bool, // Default: true
 *     },
 *     type_info?: bool|array{ // Type info configuration
 *         enabled?: bool, // Default: true
 *         aliases?: array<string, scalar|null>,
 *     },
 *     property_info?: bool|array{ // Property info configuration
 *         enabled?: bool, // Default: true
 *         with_constructor_extractor?: bool, // Registers the constructor extractor.
 *     },
 *     cache?: array{ // Cache configuration
 *         prefix_seed?: scalar|null, // Used to namespace cache keys when using several apps with the same shared backend. // Default: "_%kernel.project_dir%.%kernel.container_class%"
 *         app?: scalar|null, // App related cache pools configuration. // Default: "cache.adapter.filesystem"
 *         system?: scalar|null, // System related cache pools configuration. // Default: "cache.adapter.system"
 *         directory?: scalar|null, // Default: "%kernel.share_dir%/pools/app"
 *         default_psr6_provider?: scalar|null,
 *         default_redis_provider?: scalar|null, // Default: "redis://localhost"
 *         default_valkey_provider?: scalar|null, // Default: "valkey://localhost"
 *         default_memcached_provider?: scalar|null, // Default: "memcached://localhost"
 *         default_doctrine_dbal_provider?: scalar|null, // Default: "database_connection"
 *         default_pdo_provider?: scalar|null, // Default: null
 *         pools?: array<string, array{ // Default: []
 *             adapters?: list<scalar|null>,
 *             tags?: scalar|null, // Default: null
 *             public?: bool, // Default: false
 *             default_lifetime?: scalar|null, // Default lifetime of the pool.
 *             provider?: scalar|null, // Overwrite the setting from the default provider for this adapter.
 *             early_expiration_message_bus?: scalar|null,
 *             clearer?: scalar|null,
 *         }>,
 *     },
 *     php_errors?: array{ // PHP errors handling configuration
 *         log?: mixed, // Use the application logger instead of the PHP logger for logging PHP errors. // Default: true
 *         throw?: bool, // Throw PHP errors as \ErrorException instances. // Default: true
 *     },
 *     exceptions?: array<string, array{ // Default: []
 *         log_level?: scalar|null, // The level of log message. Null to let Symfony decide. // Default: null
 *         status_code?: scalar|null, // The status code of the response. Null or 0 to let Symfony decide. // Default: null
 *         log_channel?: scalar|null, // The channel of log message. Null to let Symfony decide. // Default: null
 *     }>,
 *     web_link?: bool|array{ // Web links configuration
 *         enabled?: bool, // Default: true
 *     },
 *     lock?: bool|string|array{ // Lock configuration
 *         enabled?: bool, // Default: false
 *         resources?: array<string, string|list<scalar|null>>,
 *     },
 *     semaphore?: bool|string|array{ // Semaphore configuration
 *         enabled?: bool, // Default: false
 *         resources?: array<string, scalar|null>,
 *     },
 *     messenger?: bool|array{ // Messenger configuration
 *         enabled?: bool, // Default: true
 *         routing?: array<string, array{ // Default: []
 *             senders?: list<scalar|null>,
 *         }>,
 *         serializer?: array{
 *             default_serializer?: scalar|null, // Service id to use as the default serializer for the transports. // Default: "messenger.transport.native_php_serializer"
 *             symfony_serializer?: array{
 *                 format?: scalar|null, // Serialization format for the messenger.transport.symfony_serializer service (which is not the serializer used by default). // Default: "json"
 *                 context?: array<string, mixed>,
 *             },
 *         },
 *         transports?: array<string, string|array{ // Default: []
 *             dsn?: scalar|null,
 *             serializer?: scalar|null, // Service id of a custom serializer to use. // Default: null
 *             options?: list<mixed>,
 *             failure_transport?: scalar|null, // Transport name to send failed messages to (after all retries have failed). // Default: null
 *             retry_strategy?: string|array{
 *                 service?: scalar|null, // Service id to override the retry strategy entirely. // Default: null
 *                 max_retries?: int, // Default: 3
 *                 delay?: int, // Time in ms to delay (or the initial value when multiplier is used). // Default: 1000
 *                 multiplier?: float, // If greater than 1, delay will grow exponentially for each retry: this delay = (delay * (multiple ^ retries)). // Default: 2
 *                 max_delay?: int, // Max time in ms that a retry should ever be delayed (0 = infinite). // Default: 0
 *                 jitter?: float, // Randomness to apply to the delay (between 0 and 1). // Default: 0.1
 *             },
 *             rate_limiter?: scalar|null, // Rate limiter name to use when processing messages. // Default: null
 *         }>,
 *         failure_transport?: scalar|null, // Transport name to send failed messages to (after all retries have failed). // Default: null
 *         stop_worker_on_signals?: list<scalar|null>,
 *         default_bus?: scalar|null, // Default: null
 *         buses?: array<string, array{ // Default: {"messenger.bus.default":{"default_middleware":{"enabled":true,"allow_no_handlers":false,"allow_no_senders":true},"middleware":[]}}
 *             default_middleware?: bool|string|array{
 *                 enabled?: bool, // Default: true
 *                 allow_no_handlers?: bool, // Default: false
 *                 allow_no_senders?: bool, // Default: true
 *             },
 *             middleware?: list<string|array{ // Default: []
 *                 id: scalar|null,
 *                 arguments?: list<mixed>,
 *             }>,
 *         }>,
 *     },
 *     scheduler?: bool|array{ // Scheduler configuration
 *         enabled?: bool, // Default: false
 *     },
 *     disallow_search_engine_index?: bool, // Enabled by default when debug is enabled. // Default: true
 *     http_client?: bool|array{ // HTTP Client configuration
 *         enabled?: bool, // Default: true
 *         max_host_connections?: int, // The maximum number of connections to a single host.
 *         default_options?: array{
 *             headers?: array<string, mixed>,
 *             vars?: array<string, mixed>,
 *             max_redirects?: int, // The maximum number of redirects to follow.
 *             http_version?: scalar|null, // The default HTTP version, typically 1.1 or 2.0, leave to null for the best version.
 *             resolve?: array<string, scalar|null>,
 *             proxy?: scalar|null, // The URL of the proxy to pass requests through or null for automatic detection.
 *             no_proxy?: scalar|null, // A comma separated list of hosts that do not require a proxy to be reached.
 *             timeout?: float, // The idle timeout, defaults to the "default_socket_timeout" ini parameter.
 *             max_duration?: float, // The maximum execution time for the request+response as a whole.
 *             bindto?: scalar|null, // A network interface name, IP address, a host name or a UNIX socket to bind to.
 *             verify_peer?: bool, // Indicates if the peer should be verified in a TLS context.
 *             verify_host?: bool, // Indicates if the host should exist as a certificate common name.
 *             cafile?: scalar|null, // A certificate authority file.
 *             capath?: scalar|null, // A directory that contains multiple certificate authority files.
 *             local_cert?: scalar|null, // A PEM formatted certificate file.
 *             local_pk?: scalar|null, // A private key file.
 *             passphrase?: scalar|null, // The passphrase used to encrypt the "local_pk" file.
 *             ciphers?: scalar|null, // A list of TLS ciphers separated by colons, commas or spaces (e.g. "RC3-SHA:TLS13-AES-128-GCM-SHA256"...)
 *             peer_fingerprint?: array{ // Associative array: hashing algorithm => hash(es).
 *                 sha1?: mixed,
 *                 pin-sha256?: mixed,
 *                 md5?: mixed,
 *             },
 *             crypto_method?: scalar|null, // The minimum version of TLS to accept; must be one of STREAM_CRYPTO_METHOD_TLSv*_CLIENT constants.
 *             extra?: array<string, mixed>,
 *             rate_limiter?: scalar|null, // Rate limiter name to use for throttling requests. // Default: null
 *             caching?: bool|array{ // Caching configuration.
 *                 enabled?: bool, // Default: false
 *                 cache_pool?: string, // The taggable cache pool to use for storing the responses. // Default: "cache.http_client"
 *                 shared?: bool, // Indicates whether the cache is shared (public) or private. // Default: true
 *                 max_ttl?: int, // The maximum TTL (in seconds) allowed for cached responses. Null means no cap. // Default: null
 *             },
 *             retry_failed?: bool|array{
 *                 enabled?: bool, // Default: false
 *                 retry_strategy?: scalar|null, // service id to override the retry strategy. // Default: null
 *                 http_codes?: array<string, array{ // Default: []
 *                     code?: int,
 *                     methods?: list<string>,
 *                 }>,
 *                 max_retries?: int, // Default: 3
 *                 delay?: int, // Time in ms to delay (or the initial value when multiplier is used). // Default: 1000
 *                 multiplier?: float, // If greater than 1, delay will grow exponentially for each retry: delay * (multiple ^ retries). // Default: 2
 *                 max_delay?: int, // Max time in ms that a retry should ever be delayed (0 = infinite). // Default: 0
 *                 jitter?: float, // Randomness in percent (between 0 and 1) to apply to the delay. // Default: 0.1
 *             },
 *         },
 *         mock_response_factory?: scalar|null, // The id of the service that should generate mock responses. It should be either an invokable or an iterable.
 *         scoped_clients?: array<string, string|array{ // Default: []
 *             scope?: scalar|null, // The regular expression that the request URL must match before adding the other options. When none is provided, the base URI is used instead.
 *             base_uri?: scalar|null, // The URI to resolve relative URLs, following rules in RFC 3985, section 2.
 *             auth_basic?: scalar|null, // An HTTP Basic authentication "username:password".
 *             auth_bearer?: scalar|null, // A token enabling HTTP Bearer authorization.
 *             auth_ntlm?: scalar|null, // A "username:password" pair to use Microsoft NTLM authentication (requires the cURL extension).
 *             query?: array<string, scalar|null>,
 *             headers?: array<string, mixed>,
 *             max_redirects?: int, // The maximum number of redirects to follow.
 *             http_version?: scalar|null, // The default HTTP version, typically 1.1 or 2.0, leave to null for the best version.
 *             resolve?: array<string, scalar|null>,
 *             proxy?: scalar|null, // The URL of the proxy to pass requests through or null for automatic detection.
 *             no_proxy?: scalar|null, // A comma separated list of hosts that do not require a proxy to be reached.
 *             timeout?: float, // The idle timeout, defaults to the "default_socket_timeout" ini parameter.
 *             max_duration?: float, // The maximum execution time for the request+response as a whole.
 *             bindto?: scalar|null, // A network interface name, IP address, a host name or a UNIX socket to bind to.
 *             verify_peer?: bool, // Indicates if the peer should be verified in a TLS context.
 *             verify_host?: bool, // Indicates if the host should exist as a certificate common name.
 *             cafile?: scalar|null, // A certificate authority file.
 *             capath?: scalar|null, // A directory that contains multiple certificate authority files.
 *             local_cert?: scalar|null, // A PEM formatted certificate file.
 *             local_pk?: scalar|null, // A private key file.
 *             passphrase?: scalar|null, // The passphrase used to encrypt the "local_pk" file.
 *             ciphers?: scalar|null, // A list of TLS ciphers separated by colons, commas or spaces (e.g. "RC3-SHA:TLS13-AES-128-GCM-SHA256"...).
 *             peer_fingerprint?: array{ // Associative array: hashing algorithm => hash(es).
 *                 sha1?: mixed,
 *                 pin-sha256?: mixed,
 *                 md5?: mixed,
 *             },
 *             crypto_method?: scalar|null, // The minimum version of TLS to accept; must be one of STREAM_CRYPTO_METHOD_TLSv*_CLIENT constants.
 *             extra?: array<string, mixed>,
 *             rate_limiter?: scalar|null, // Rate limiter name to use for throttling requests. // Default: null
 *             caching?: bool|array{ // Caching configuration.
 *                 enabled?: bool, // Default: false
 *                 cache_pool?: string, // The taggable cache pool to use for storing the responses. // Default: "cache.http_client"
 *                 shared?: bool, // Indicates whether the cache is shared (public) or private. // Default: true
 *                 max_ttl?: int, // The maximum TTL (in seconds) allowed for cached responses. Null means no cap. // Default: null
 *             },
 *             retry_failed?: bool|array{
 *                 enabled?: bool, // Default: false
 *                 retry_strategy?: scalar|null, // service id to override the retry strategy. // Default: null
 *                 http_codes?: array<string, array{ // Default: []
 *                     code?: int,
 *                     methods?: list<string>,
 *                 }>,
 *                 max_retries?: int, // Default: 3
 *                 delay?: int, // Time in ms to delay (or the initial value when multiplier is used). // Default: 1000
 *                 multiplier?: float, // If greater than 1, delay will grow exponentially for each retry: delay * (multiple ^ retries). // Default: 2
 *                 max_delay?: int, // Max time in ms that a retry should ever be delayed (0 = infinite). // Default: 0
 *                 jitter?: float, // Randomness in percent (between 0 and 1) to apply to the delay. // Default: 0.1
 *             },
 *         }>,
 *     },
 *     mailer?: bool|array{ // Mailer configuration
 *         enabled?: bool, // Default: true
 *         message_bus?: scalar|null, // The message bus to use. Defaults to the default bus if the Messenger component is installed. // Default: null
 *         dsn?: scalar|null, // Default: null
 *         transports?: array<string, scalar|null>,
 *         envelope?: array{ // Mailer Envelope configuration
 *             sender?: scalar|null,
 *             recipients?: list<scalar|null>,
 *             allowed_recipients?: list<scalar|null>,
 *         },
 *         headers?: array<string, string|array{ // Default: []
 *             value?: mixed,
 *         }>,
 *         dkim_signer?: bool|array{ // DKIM signer configuration
 *             enabled?: bool, // Default: false
 *             key?: scalar|null, // Key content, or path to key (in PEM format with the `file://` prefix) // Default: ""
 *             domain?: scalar|null, // Default: ""
 *             select?: scalar|null, // Default: ""
 *             passphrase?: scalar|null, // The private key passphrase // Default: ""
 *             options?: array<string, mixed>,
 *         },
 *         smime_signer?: bool|array{ // S/MIME signer configuration
 *             enabled?: bool, // Default: false
 *             key?: scalar|null, // Path to key (in PEM format) // Default: ""
 *             certificate?: scalar|null, // Path to certificate (in PEM format without the `file://` prefix) // Default: ""
 *             passphrase?: scalar|null, // The private key passphrase // Default: null
 *             extra_certificates?: scalar|null, // Default: null
 *             sign_options?: int, // Default: null
 *         },
 *         smime_encrypter?: bool|array{ // S/MIME encrypter configuration
 *             enabled?: bool, // Default: false
 *             repository?: scalar|null, // S/MIME certificate repository service. This service shall implement the `Symfony\Component\Mailer\EventListener\SmimeCertificateRepositoryInterface`. // Default: ""
 *             cipher?: int, // A set of algorithms used to encrypt the message // Default: null
 *         },
 *     },
 *     secrets?: bool|array{
 *         enabled?: bool, // Default: true
 *         vault_directory?: scalar|null, // Default: "%kernel.project_dir%/config/secrets/%kernel.runtime_environment%"
 *         local_dotenv_file?: scalar|null, // Default: "%kernel.project_dir%/.env.%kernel.runtime_environment%.local"
 *         decryption_env_var?: scalar|null, // Default: "base64:default::SYMFONY_DECRYPTION_SECRET"
 *     },
 *     notifier?: bool|array{ // Notifier configuration
 *         enabled?: bool, // Default: true
 *         message_bus?: scalar|null, // The message bus to use. Defaults to the default bus if the Messenger component is installed. // Default: null
 *         chatter_transports?: array<string, scalar|null>,
 *         texter_transports?: array<string, scalar|null>,
 *         notification_on_failed_messages?: bool, // Default: false
 *         channel_policy?: array<string, string|list<scalar|null>>,
 *         admin_recipients?: list<array{ // Default: []
 *             email?: scalar|null,
 *             phone?: scalar|null, // Default: ""
 *         }>,
 *     },
 *     rate_limiter?: bool|array{ // Rate limiter configuration
 *         enabled?: bool, // Default: false
 *         limiters?: array<string, array{ // Default: []
 *             lock_factory?: scalar|null, // The service ID of the lock factory used by this limiter (or null to disable locking). // Default: "auto"
 *             cache_pool?: scalar|null, // The cache pool to use for storing the current limiter state. // Default: "cache.rate_limiter"
 *             storage_service?: scalar|null, // The service ID of a custom storage implementation, this precedes any configured "cache_pool". // Default: null
 *             policy: "fixed_window"|"token_bucket"|"sliding_window"|"compound"|"no_limit", // The algorithm to be used by this limiter.
 *             limiters?: list<scalar|null>,
 *             limit?: int, // The maximum allowed hits in a fixed interval or burst.
 *             interval?: scalar|null, // Configures the fixed interval if "policy" is set to "fixed_window" or "sliding_window". The value must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent).
 *             rate?: array{ // Configures the fill rate if "policy" is set to "token_bucket".
 *                 interval?: scalar|null, // Configures the rate interval. The value must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent).
 *                 amount?: int, // Amount of tokens to add each interval. // Default: 1
 *             },
 *         }>,
 *     },
 *     uid?: bool|array{ // Uid configuration
 *         enabled?: bool, // Default: false
 *         default_uuid_version?: 7|6|4|1, // Default: 7
 *         name_based_uuid_version?: 5|3, // Default: 5
 *         name_based_uuid_namespace?: scalar|null,
 *         time_based_uuid_version?: 7|6|1, // Default: 7
 *         time_based_uuid_node?: scalar|null,
 *     },
 *     html_sanitizer?: bool|array{ // HtmlSanitizer configuration
 *         enabled?: bool, // Default: false
 *         sanitizers?: array<string, array{ // Default: []
 *             allow_safe_elements?: bool, // Allows "safe" elements and attributes. // Default: false
 *             allow_static_elements?: bool, // Allows all static elements and attributes from the W3C Sanitizer API standard. // Default: false
 *             allow_elements?: array<string, mixed>,
 *             block_elements?: list<string>,
 *             drop_elements?: list<string>,
 *             allow_attributes?: array<string, mixed>,
 *             drop_attributes?: array<string, mixed>,
 *             force_attributes?: array<string, array<string, string>>,
 *             force_https_urls?: bool, // Transforms URLs using the HTTP scheme to use the HTTPS scheme instead. // Default: false
 *             allowed_link_schemes?: list<string>,
 *             allowed_link_hosts?: list<string>|null,
 *             allow_relative_links?: bool, // Allows relative URLs to be used in links href attributes. // Default: false
 *             allowed_media_schemes?: list<string>,
 *             allowed_media_hosts?: list<string>|null,
 *             allow_relative_medias?: bool, // Allows relative URLs to be used in media source attributes (img, audio, video, ...). // Default: false
 *             with_attribute_sanitizers?: list<string>,
 *             without_attribute_sanitizers?: list<string>,
 *             max_input_length?: int, // The maximum length allowed for the sanitized input. // Default: 0
 *         }>,
 *     },
 *     webhook?: bool|array{ // Webhook configuration
 *         enabled?: bool, // Default: false
 *         message_bus?: scalar|null, // The message bus to use. // Default: "messenger.default_bus"
 *         routing?: array<string, array{ // Default: []
 *             service: scalar|null,
 *             secret?: scalar|null, // Default: ""
 *         }>,
 *     },
 *     remote-event?: bool|array{ // RemoteEvent configuration
 *         enabled?: bool, // Default: false
 *     },
 *     json_streamer?: bool|array{ // JSON streamer configuration
 *         enabled?: bool, // Default: false
 *     },
 * }
 * @psalm-type TwigConfig = array{
 *     form_themes?: list<scalar|null>,
 *     globals?: array<string, array{ // Default: []
 *         id?: scalar|null,
 *         type?: scalar|null,
 *         value?: mixed,
 *     }>,
 *     autoescape_service?: scalar|null, // Default: null
 *     autoescape_service_method?: scalar|null, // Default: null
 *     base_template_class?: scalar|null, // Deprecated: The child node "base_template_class" at path "twig.base_template_class" is deprecated.
 *     cache?: scalar|null, // Default: true
 *     charset?: scalar|null, // Default: "%kernel.charset%"
 *     debug?: bool, // Default: "%kernel.debug%"
 *     strict_variables?: bool, // Default: "%kernel.debug%"
 *     auto_reload?: scalar|null,
 *     optimizations?: int,
 *     default_path?: scalar|null, // The default path used to load templates. // Default: "%kernel.project_dir%/templates"
 *     file_name_pattern?: list<scalar|null>,
 *     paths?: array<string, mixed>,
 *     date?: array{ // The default format options used by the date filter.
 *         format?: scalar|null, // Default: "F j, Y H:i"
 *         interval_format?: scalar|null, // Default: "%d days"
 *         timezone?: scalar|null, // The timezone used when formatting dates, when set to null, the timezone returned by date_default_timezone_get() is used. // Default: null
 *     },
 *     number_format?: array{ // The default format options for the number_format filter.
 *         decimals?: int, // Default: 0
 *         decimal_point?: scalar|null, // Default: "."
 *         thousands_separator?: scalar|null, // Default: ","
 *     },
 *     mailer?: array{
 *         html_to_text_converter?: scalar|null, // A service implementing the "Symfony\Component\Mime\HtmlToTextConverter\HtmlToTextConverterInterface". // Default: null
 *     },
 * }
 * @psalm-type SecurityConfig = array{
 *     access_denied_url?: scalar|null, // Default: null
 *     session_fixation_strategy?: "none"|"migrate"|"invalidate", // Default: "migrate"
 *     hide_user_not_found?: bool, // Deprecated: The "hide_user_not_found" option is deprecated and will be removed in 8.0. Use the "expose_security_errors" option instead.
 *     expose_security_errors?: \Symfony\Component\Security\Http\Authentication\ExposeSecurityLevel::None|\Symfony\Component\Security\Http\Authentication\ExposeSecurityLevel::AccountStatus|\Symfony\Component\Security\Http\Authentication\ExposeSecurityLevel::All, // Default: "none"
 *     erase_credentials?: bool, // Default: true
 *     access_decision_manager?: array{
 *         strategy?: "affirmative"|"consensus"|"unanimous"|"priority",
 *         service?: scalar|null,
 *         strategy_service?: scalar|null,
 *         allow_if_all_abstain?: bool, // Default: false
 *         allow_if_equal_granted_denied?: bool, // Default: true
 *     },
 *     password_hashers?: array<string, string|array{ // Default: []
 *         algorithm?: scalar|null,
 *         migrate_from?: list<scalar|null>,
 *         hash_algorithm?: scalar|null, // Name of hashing algorithm for PBKDF2 (i.e. sha256, sha512, etc..) See hash_algos() for a list of supported algorithms. // Default: "sha512"
 *         key_length?: scalar|null, // Default: 40
 *         ignore_case?: bool, // Default: false
 *         encode_as_base64?: bool, // Default: true
 *         iterations?: scalar|null, // Default: 5000
 *         cost?: int, // Default: null
 *         memory_cost?: scalar|null, // Default: null
 *         time_cost?: scalar|null, // Default: null
 *         id?: scalar|null,
 *     }>,
 *     providers?: array<string, array{ // Default: []
 *         id?: scalar|null,
 *         chain?: array{
 *             providers?: list<scalar|null>,
 *         },
 *         entity?: array{
 *             class: scalar|null, // The full entity class name of your user class.
 *             property?: scalar|null, // Default: null
 *             manager_name?: scalar|null, // Default: null
 *         },
 *         memory?: array{
 *             users?: array<string, array{ // Default: []
 *                 password?: scalar|null, // Default: null
 *                 roles?: list<scalar|null>,
 *             }>,
 *         },
 *         ldap?: array{
 *             service: scalar|null,
 *             base_dn: scalar|null,
 *             search_dn?: scalar|null, // Default: null
 *             search_password?: scalar|null, // Default: null
 *             extra_fields?: list<scalar|null>,
 *             default_roles?: list<scalar|null>,
 *             role_fetcher?: scalar|null, // Default: null
 *             uid_key?: scalar|null, // Default: "sAMAccountName"
 *             filter?: scalar|null, // Default: "({uid_key}={user_identifier})"
 *             password_attribute?: scalar|null, // Default: null
 *         },
 *     }>,
 *     firewalls: array<string, array{ // Default: []
 *         pattern?: scalar|null,
 *         host?: scalar|null,
 *         methods?: list<scalar|null>,
 *         security?: bool, // Default: true
 *         user_checker?: scalar|null, // The UserChecker to use when authenticating users in this firewall. // Default: "security.user_checker"
 *         request_matcher?: scalar|null,
 *         access_denied_url?: scalar|null,
 *         access_denied_handler?: scalar|null,
 *         entry_point?: scalar|null, // An enabled authenticator name or a service id that implements "Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface".
 *         provider?: scalar|null,
 *         stateless?: bool, // Default: false
 *         lazy?: bool, // Default: false
 *         context?: scalar|null,
 *         logout?: array{
 *             enable_csrf?: bool|null, // Default: null
 *             csrf_token_id?: scalar|null, // Default: "logout"
 *             csrf_parameter?: scalar|null, // Default: "_csrf_token"
 *             csrf_token_manager?: scalar|null,
 *             path?: scalar|null, // Default: "/logout"
 *             target?: scalar|null, // Default: "/"
 *             invalidate_session?: bool, // Default: true
 *             clear_site_data?: list<"*"|"cache"|"cookies"|"storage"|"executionContexts">,
 *             delete_cookies?: array<string, array{ // Default: []
 *                 path?: scalar|null, // Default: null
 *                 domain?: scalar|null, // Default: null
 *                 secure?: scalar|null, // Default: false
 *                 samesite?: scalar|null, // Default: null
 *                 partitioned?: scalar|null, // Default: false
 *             }>,
 *         },
 *         switch_user?: array{
 *             provider?: scalar|null,
 *             parameter?: scalar|null, // Default: "_switch_user"
 *             role?: scalar|null, // Default: "ROLE_ALLOWED_TO_SWITCH"
 *             target_route?: scalar|null, // Default: null
 *         },
 *         required_badges?: list<scalar|null>,
 *         custom_authenticators?: list<scalar|null>,
 *         login_throttling?: array{
 *             limiter?: scalar|null, // A service id implementing "Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface".
 *             max_attempts?: int, // Default: 5
 *             interval?: scalar|null, // Default: "1 minute"
 *             lock_factory?: scalar|null, // The service ID of the lock factory used by the login rate limiter (or null to disable locking). // Default: null
 *             cache_pool?: string, // The cache pool to use for storing the limiter state // Default: "cache.rate_limiter"
 *             storage_service?: string, // The service ID of a custom storage implementation, this precedes any configured "cache_pool" // Default: null
 *         },
 *         x509?: array{
 *             provider?: scalar|null,
 *             user?: scalar|null, // Default: "SSL_CLIENT_S_DN_Email"
 *             credentials?: scalar|null, // Default: "SSL_CLIENT_S_DN"
 *             user_identifier?: scalar|null, // Default: "emailAddress"
 *         },
 *         remote_user?: array{
 *             provider?: scalar|null,
 *             user?: scalar|null, // Default: "REMOTE_USER"
 *         },
 *         oauth2?: array<mixed>,
 *         login_link?: array{
 *             check_route: scalar|null, // Route that will validate the login link - e.g. "app_login_link_verify".
 *             check_post_only?: scalar|null, // If true, only HTTP POST requests to "check_route" will be handled by the authenticator. // Default: false
 *             signature_properties: list<scalar|null>,
 *             lifetime?: int, // The lifetime of the login link in seconds. // Default: 600
 *             max_uses?: int, // Max number of times a login link can be used - null means unlimited within lifetime. // Default: null
 *             used_link_cache?: scalar|null, // Cache service id used to expired links of max_uses is set.
 *             success_handler?: scalar|null, // A service id that implements Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface.
 *             failure_handler?: scalar|null, // A service id that implements Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface.
 *             provider?: scalar|null, // The user provider to load users from.
 *             secret?: scalar|null, // Default: "%kernel.secret%"
 *             always_use_default_target_path?: bool, // Default: false
 *             default_target_path?: scalar|null, // Default: "/"
 *             login_path?: scalar|null, // Default: "/login"
 *             target_path_parameter?: scalar|null, // Default: "_target_path"
 *             use_referer?: bool, // Default: false
 *             failure_path?: scalar|null, // Default: null
 *             failure_forward?: bool, // Default: false
 *             failure_path_parameter?: scalar|null, // Default: "_failure_path"
 *         },
 *         form_login?: array{
 *             provider?: scalar|null,
 *             remember_me?: bool, // Default: true
 *             success_handler?: scalar|null,
 *             failure_handler?: scalar|null,
 *             check_path?: scalar|null, // Default: "/login_check"
 *             use_forward?: bool, // Default: false
 *             login_path?: scalar|null, // Default: "/login"
 *             username_parameter?: scalar|null, // Default: "_username"
 *             password_parameter?: scalar|null, // Default: "_password"
 *             csrf_parameter?: scalar|null, // Default: "_csrf_token"
 *             csrf_token_id?: scalar|null, // Default: "authenticate"
 *             enable_csrf?: bool, // Default: false
 *             post_only?: bool, // Default: true
 *             form_only?: bool, // Default: false
 *             always_use_default_target_path?: bool, // Default: false
 *             default_target_path?: scalar|null, // Default: "/"
 *             target_path_parameter?: scalar|null, // Default: "_target_path"
 *             use_referer?: bool, // Default: false
 *             failure_path?: scalar|null, // Default: null
 *             failure_forward?: bool, // Default: false
 *             failure_path_parameter?: scalar|null, // Default: "_failure_path"
 *         },
 *         form_login_ldap?: array{
 *             provider?: scalar|null,
 *             remember_me?: bool, // Default: true
 *             success_handler?: scalar|null,
 *             failure_handler?: scalar|null,
 *             check_path?: scalar|null, // Default: "/login_check"
 *             use_forward?: bool, // Default: false
 *             login_path?: scalar|null, // Default: "/login"
 *             username_parameter?: scalar|null, // Default: "_username"
 *             password_parameter?: scalar|null, // Default: "_password"
 *             csrf_parameter?: scalar|null, // Default: "_csrf_token"
 *             csrf_token_id?: scalar|null, // Default: "authenticate"
 *             enable_csrf?: bool, // Default: false
 *             post_only?: bool, // Default: true
 *             form_only?: bool, // Default: false
 *             always_use_default_target_path?: bool, // Default: false
 *             default_target_path?: scalar|null, // Default: "/"
 *             target_path_parameter?: scalar|null, // Default: "_target_path"
 *             use_referer?: bool, // Default: false
 *             failure_path?: scalar|null, // Default: null
 *             failure_forward?: bool, // Default: false
 *             failure_path_parameter?: scalar|null, // Default: "_failure_path"
 *             service?: scalar|null, // Default: "ldap"
 *             dn_string?: scalar|null, // Default: "{user_identifier}"
 *             query_string?: scalar|null,
 *             search_dn?: scalar|null, // Default: ""
 *             search_password?: scalar|null, // Default: ""
 *         },
 *         json_login?: array{
 *             provider?: scalar|null,
 *             remember_me?: bool, // Default: true
 *             success_handler?: scalar|null,
 *             failure_handler?: scalar|null,
 *             check_path?: scalar|null, // Default: "/login_check"
 *             use_forward?: bool, // Default: false
 *             login_path?: scalar|null, // Default: "/login"
 *             username_path?: scalar|null, // Default: "username"
 *             password_path?: scalar|null, // Default: "password"
 *         },
 *         json_login_ldap?: array{
 *             provider?: scalar|null,
 *             remember_me?: bool, // Default: true
 *             success_handler?: scalar|null,
 *             failure_handler?: scalar|null,
 *             check_path?: scalar|null, // Default: "/login_check"
 *             use_forward?: bool, // Default: false
 *             login_path?: scalar|null, // Default: "/login"
 *             username_path?: scalar|null, // Default: "username"
 *             password_path?: scalar|null, // Default: "password"
 *             service?: scalar|null, // Default: "ldap"
 *             dn_string?: scalar|null, // Default: "{user_identifier}"
 *             query_string?: scalar|null,
 *             search_dn?: scalar|null, // Default: ""
 *             search_password?: scalar|null, // Default: ""
 *         },
 *         access_token?: array{
 *             provider?: scalar|null,
 *             remember_me?: bool, // Default: true
 *             success_handler?: scalar|null,
 *             failure_handler?: scalar|null,
 *             realm?: scalar|null, // Default: null
 *             token_extractors?: list<scalar|null>,
 *             token_handler: string|array{
 *                 id?: scalar|null,
 *                 oidc_user_info?: string|array{
 *                     base_uri: scalar|null, // Base URI of the userinfo endpoint on the OIDC server, or the OIDC server URI to use the discovery (require "discovery" to be configured).
 *                     discovery?: array{ // Enable the OIDC discovery.
 *                         cache?: array{
 *                             id: scalar|null, // Cache service id to use to cache the OIDC discovery configuration.
 *                         },
 *                     },
 *                     claim?: scalar|null, // Claim which contains the user identifier (e.g. sub, email, etc.). // Default: "sub"
 *                     client?: scalar|null, // HttpClient service id to use to call the OIDC server.
 *                 },
 *                 oidc?: array{
 *                     discovery?: array{ // Enable the OIDC discovery.
 *                         base_uri: list<scalar|null>,
 *                         cache?: array{
 *                             id: scalar|null, // Cache service id to use to cache the OIDC discovery configuration.
 *                         },
 *                     },
 *                     claim?: scalar|null, // Claim which contains the user identifier (e.g.: sub, email..). // Default: "sub"
 *                     audience: scalar|null, // Audience set in the token, for validation purpose.
 *                     issuers: list<scalar|null>,
 *                     algorithm?: array<mixed>,
 *                     algorithms: list<scalar|null>,
 *                     key?: scalar|null, // Deprecated: The "key" option is deprecated and will be removed in 8.0. Use the "keyset" option instead. // JSON-encoded JWK used to sign the token (must contain a "kty" key).
 *                     keyset?: scalar|null, // JSON-encoded JWKSet used to sign the token (must contain a list of valid public keys).
 *                     encryption?: bool|array{
 *                         enabled?: bool, // Default: false
 *                         enforce?: bool, // When enabled, the token shall be encrypted. // Default: false
 *                         algorithms: list<scalar|null>,
 *                         keyset: scalar|null, // JSON-encoded JWKSet used to decrypt the token (must contain a list of valid private keys).
 *                     },
 *                 },
 *                 cas?: array{
 *                     validation_url: scalar|null, // CAS server validation URL
 *                     prefix?: scalar|null, // CAS prefix // Default: "cas"
 *                     http_client?: scalar|null, // HTTP Client service // Default: null
 *                 },
 *                 oauth2?: scalar|null,
 *             },
 *         },
 *         http_basic?: array{
 *             provider?: scalar|null,
 *             realm?: scalar|null, // Default: "Secured Area"
 *         },
 *         http_basic_ldap?: array{
 *             provider?: scalar|null,
 *             realm?: scalar|null, // Default: "Secured Area"
 *             service?: scalar|null, // Default: "ldap"
 *             dn_string?: scalar|null, // Default: "{user_identifier}"
 *             query_string?: scalar|null,
 *             search_dn?: scalar|null, // Default: ""
 *             search_password?: scalar|null, // Default: ""
 *         },
 *         remember_me?: array{
 *             secret?: scalar|null, // Default: "%kernel.secret%"
 *             service?: scalar|null,
 *             user_providers?: list<scalar|null>,
 *             catch_exceptions?: bool, // Default: true
 *             signature_properties?: list<scalar|null>,
 *             token_provider?: string|array{
 *                 service?: scalar|null, // The service ID of a custom remember-me token provider.
 *                 doctrine?: bool|array{
 *                     enabled?: bool, // Default: false
 *                     connection?: scalar|null, // Default: null
 *                 },
 *             },
 *             token_verifier?: scalar|null, // The service ID of a custom rememberme token verifier.
 *             name?: scalar|null, // Default: "REMEMBERME"
 *             lifetime?: int, // Default: 31536000
 *             path?: scalar|null, // Default: "/"
 *             domain?: scalar|null, // Default: null
 *             secure?: true|false|"auto", // Default: null
 *             httponly?: bool, // Default: true
 *             samesite?: null|"lax"|"strict"|"none", // Default: "lax"
 *             always_remember_me?: bool, // Default: false
 *             remember_me_parameter?: scalar|null, // Default: "_remember_me"
 *         },
 *     }>,
 *     access_control?: list<array{ // Default: []
 *         request_matcher?: scalar|null, // Default: null
 *         requires_channel?: scalar|null, // Default: null
 *         path?: scalar|null, // Use the urldecoded format. // Default: null
 *         host?: scalar|null, // Default: null
 *         port?: int, // Default: null
 *         ips?: list<scalar|null>,
 *         attributes?: array<string, scalar|null>,
 *         route?: scalar|null, // Default: null
 *         methods?: list<scalar|null>,
 *         allow_if?: scalar|null, // Default: null
 *         roles?: list<scalar|null>,
 *     }>,
 *     role_hierarchy?: array<string, string|list<scalar|null>>,
 * }
 * @psalm-type MonologConfig = array{
 *     use_microseconds?: scalar|null, // Default: true
 *     channels?: list<scalar|null>,
 *     handlers?: array<string, array{ // Default: []
 *         type: scalar|null,
 *         id?: scalar|null,
 *         enabled?: bool, // Default: true
 *         priority?: scalar|null, // Default: 0
 *         level?: scalar|null, // Default: "DEBUG"
 *         bubble?: bool, // Default: true
 *         interactive_only?: bool, // Default: false
 *         app_name?: scalar|null, // Default: null
 *         fill_extra_context?: bool, // Default: false
 *         include_stacktraces?: bool, // Default: false
 *         process_psr_3_messages?: array{
 *             enabled?: bool|null, // Default: null
 *             date_format?: scalar|null,
 *             remove_used_context_fields?: bool,
 *         },
 *         path?: scalar|null, // Default: "%kernel.logs_dir%/%kernel.environment%.log"
 *         file_permission?: scalar|null, // Default: null
 *         use_locking?: bool, // Default: false
 *         filename_format?: scalar|null, // Default: "{filename}-{date}"
 *         date_format?: scalar|null, // Default: "Y-m-d"
 *         ident?: scalar|null, // Default: false
 *         logopts?: scalar|null, // Default: 1
 *         facility?: scalar|null, // Default: "user"
 *         max_files?: scalar|null, // Default: 0
 *         action_level?: scalar|null, // Default: "WARNING"
 *         activation_strategy?: scalar|null, // Default: null
 *         stop_buffering?: bool, // Default: true
 *         passthru_level?: scalar|null, // Default: null
 *         excluded_404s?: list<scalar|null>,
 *         excluded_http_codes?: list<array{ // Default: []
 *             code?: scalar|null,
 *             urls?: list<scalar|null>,
 *         }>,
 *         accepted_levels?: list<scalar|null>,
 *         min_level?: scalar|null, // Default: "DEBUG"
 *         max_level?: scalar|null, // Default: "EMERGENCY"
 *         buffer_size?: scalar|null, // Default: 0
 *         flush_on_overflow?: bool, // Default: false
 *         handler?: scalar|null,
 *         url?: scalar|null,
 *         exchange?: scalar|null,
 *         exchange_name?: scalar|null, // Default: "log"
 *         room?: scalar|null,
 *         message_format?: scalar|null, // Default: "text"
 *         api_version?: scalar|null, // Default: null
 *         channel?: scalar|null, // Default: null
 *         bot_name?: scalar|null, // Default: "Monolog"
 *         use_attachment?: scalar|null, // Default: true
 *         use_short_attachment?: scalar|null, // Default: false
 *         include_extra?: scalar|null, // Default: false
 *         icon_emoji?: scalar|null, // Default: null
 *         webhook_url?: scalar|null,
 *         exclude_fields?: list<scalar|null>,
 *         team?: scalar|null,
 *         notify?: scalar|null, // Default: false
 *         nickname?: scalar|null, // Default: "Monolog"
 *         token?: scalar|null,
 *         region?: scalar|null,
 *         source?: scalar|null,
 *         use_ssl?: bool, // Default: true
 *         user?: mixed,
 *         title?: scalar|null, // Default: null
 *         host?: scalar|null, // Default: null
 *         port?: scalar|null, // Default: 514
 *         config?: list<scalar|null>,
 *         members?: list<scalar|null>,
 *         connection_string?: scalar|null,
 *         timeout?: scalar|null,
 *         time?: scalar|null, // Default: 60
 *         deduplication_level?: scalar|null, // Default: 400
 *         store?: scalar|null, // Default: null
 *         connection_timeout?: scalar|null,
 *         persistent?: bool,
 *         dsn?: scalar|null,
 *         hub_id?: scalar|null, // Default: null
 *         client_id?: scalar|null, // Default: null
 *         auto_log_stacks?: scalar|null, // Default: false
 *         release?: scalar|null, // Default: null
 *         environment?: scalar|null, // Default: null
 *         message_type?: scalar|null, // Default: 0
 *         parse_mode?: scalar|null, // Default: null
 *         disable_webpage_preview?: bool|null, // Default: null
 *         disable_notification?: bool|null, // Default: null
 *         split_long_messages?: bool, // Default: false
 *         delay_between_messages?: bool, // Default: false
 *         topic?: int, // Default: null
 *         factor?: int, // Default: 1
 *         tags?: list<scalar|null>,
 *         console_formater_options?: mixed, // Deprecated: "monolog.handlers..console_formater_options.console_formater_options" is deprecated, use "monolog.handlers..console_formater_options.console_formatter_options" instead.
 *         console_formatter_options?: mixed, // Default: []
 *         formatter?: scalar|null,
 *         nested?: bool, // Default: false
 *         publisher?: string|array{
 *             id?: scalar|null,
 *             hostname?: scalar|null,
 *             port?: scalar|null, // Default: 12201
 *             chunk_size?: scalar|null, // Default: 1420
 *             encoder?: "json"|"compressed_json",
 *         },
 *         mongo?: string|array{
 *             id?: scalar|null,
 *             host?: scalar|null,
 *             port?: scalar|null, // Default: 27017
 *             user?: scalar|null,
 *             pass?: scalar|null,
 *             database?: scalar|null, // Default: "monolog"
 *             collection?: scalar|null, // Default: "logs"
 *         },
 *         mongodb?: string|array{
 *             id?: scalar|null, // ID of a MongoDB\Client service
 *             uri?: scalar|null,
 *             username?: scalar|null,
 *             password?: scalar|null,
 *             database?: scalar|null, // Default: "monolog"
 *             collection?: scalar|null, // Default: "logs"
 *         },
 *         elasticsearch?: string|array{
 *             id?: scalar|null,
 *             hosts?: list<scalar|null>,
 *             host?: scalar|null,
 *             port?: scalar|null, // Default: 9200
 *             transport?: scalar|null, // Default: "Http"
 *             user?: scalar|null, // Default: null
 *             password?: scalar|null, // Default: null
 *         },
 *         index?: scalar|null, // Default: "monolog"
 *         document_type?: scalar|null, // Default: "logs"
 *         ignore_error?: scalar|null, // Default: false
 *         redis?: string|array{
 *             id?: scalar|null,
 *             host?: scalar|null,
 *             password?: scalar|null, // Default: null
 *             port?: scalar|null, // Default: 6379
 *             database?: scalar|null, // Default: 0
 *             key_name?: scalar|null, // Default: "monolog_redis"
 *         },
 *         predis?: string|array{
 *             id?: scalar|null,
 *             host?: scalar|null,
 *         },
 *         from_email?: scalar|null,
 *         to_email?: list<scalar|null>,
 *         subject?: scalar|null,
 *         content_type?: scalar|null, // Default: null
 *         headers?: list<scalar|null>,
 *         mailer?: scalar|null, // Default: null
 *         email_prototype?: string|array{
 *             id: scalar|null,
 *             method?: scalar|null, // Default: null
 *         },
 *         lazy?: bool, // Default: true
 *         verbosity_levels?: array{
 *             VERBOSITY_QUIET?: scalar|null, // Default: "ERROR"
 *             VERBOSITY_NORMAL?: scalar|null, // Default: "WARNING"
 *             VERBOSITY_VERBOSE?: scalar|null, // Default: "NOTICE"
 *             VERBOSITY_VERY_VERBOSE?: scalar|null, // Default: "INFO"
 *             VERBOSITY_DEBUG?: scalar|null, // Default: "DEBUG"
 *         },
 *         channels?: string|array{
 *             type?: scalar|null,
 *             elements?: list<scalar|null>,
 *         },
 *     }>,
 * }
 * @psalm-type MakerConfig = array{
 *     root_namespace?: scalar|null, // Default: "App"
 *     generate_final_classes?: bool, // Default: true
 *     generate_final_entities?: bool, // Default: false
 * }
 * @psalm-type CmfRoutingConfig = array{
 *     chain?: array{
 *         routers_by_id?: array<string, scalar|null>,
 *         replace_symfony_router?: bool, // Default: true
 *     },
 *     dynamic?: bool|array{
 *         enabled?: bool, // Default: false
 *         route_collection_limit?: scalar|null, // Default: 0
 *         generic_controller?: scalar|null, // Default: null
 *         default_controller?: scalar|null, // Default: null
 *         controllers_by_type?: array<string, scalar|null>,
 *         controllers_by_class?: array<string, scalar|null>,
 *         templates_by_class?: array<string, scalar|null>,
 *         persistence?: array{
 *             phpcr?: bool|array{
 *                 enabled?: bool, // Default: false
 *                 manager_name?: scalar|null, // Default: null
 *                 route_basepaths?: list<scalar|null>,
 *                 enable_initializer?: bool, // Default: true
 *             },
 *             orm?: bool|array{
 *                 enabled?: bool, // Default: false
 *                 manager_name?: scalar|null, // Default: null
 *                 route_class?: scalar|null, // Default: "Symfony\\Cmf\\Bundle\\RoutingBundle\\Doctrine\\Orm\\Route"
 *             },
 *         },
 *         uri_filter_regexp?: scalar|null, // Default: ""
 *         route_provider_service_id?: scalar|null,
 *         route_filters_by_id?: array<string, scalar|null>,
 *         content_repository_service_id?: scalar|null,
 *         locales?: list<scalar|null>,
 *         limit_candidates?: int, // Default: 20
 *         match_implicit_locale?: bool, // Default: true
 *         redirectable_url_matcher?: bool, // Default: false
 *         auto_locale_pattern?: bool, // Default: false
 *         url_generator?: scalar|null, // URL generator service ID // Default: "cmf_routing.generator"
 *     },
 * }
 * @psalm-type SonataBlockConfig = array{
 *     profiler?: array{
 *         enabled?: scalar|null, // Default: "%kernel.debug%"
 *         template?: scalar|null, // Default: "@SonataBlock/Profiler/block.html.twig"
 *     },
 *     default_contexts?: list<scalar|null>,
 *     context_manager?: scalar|null, // Default: "sonata.block.context_manager.default"
 *     http_cache?: bool, // Deprecated: The "http_cache" option is deprecated and not doing anything anymore since sonata-project/block-bundle 5.0. It will be removed in 6.0. // Default: false
 *     templates?: array{
 *         block_base?: scalar|null, // Default: null
 *         block_container?: scalar|null, // Default: null
 *     },
 *     container?: array{ // block container configuration
 *         types?: list<scalar|null>,
 *         templates?: list<scalar|null>,
 *     },
 *     blocks?: array<string, array{ // Default: []
 *         contexts?: list<scalar|null>,
 *         templates?: list<array{ // Default: []
 *             name?: scalar|null,
 *             template?: scalar|null,
 *         }>,
 *         settings?: array<string, scalar|null>,
 *         exception?: array{
 *             filter?: scalar|null, // Default: null
 *             renderer?: scalar|null, // Default: null
 *         },
 *     }>,
 *     blocks_by_class?: array<string, array{ // Default: []
 *         settings?: array<string, scalar|null>,
 *     }>,
 *     exception?: array{
 *         default?: array{
 *             filter?: scalar|null, // Default: "debug_only"
 *             renderer?: scalar|null, // Default: "throw"
 *         },
 *         filters?: array<string, scalar|null>,
 *         renderers?: array<string, scalar|null>,
 *     },
 * }
 * @psalm-type SonataAdminConfig = array{
 *     security?: array{
 *         handler?: scalar|null, // Default: "sonata.admin.security.handler.noop"
 *         information?: array<string, string|list<scalar|null>>,
 *         admin_permissions?: list<scalar|null>,
 *         role_admin?: scalar|null, // Role which will see the top nav bar and dropdown groups regardless of its configuration // Default: "ROLE_SONATA_ADMIN"
 *         role_super_admin?: scalar|null, // Role which will perform all admin actions, see dashboard, menu and search groups regardless of its configuration // Default: "ROLE_SUPER_ADMIN"
 *         object_permissions?: list<scalar|null>,
 *         acl_user_manager?: scalar|null, // Default: null
 *     },
 *     title?: scalar|null, // Default: "Sonata Admin"
 *     title_logo?: scalar|null, // Default: "bundles/sonataadmin/images/logo_title.png"
 *     search?: bool, // Enable/disable the search form in the sidebar // Default: true
 *     global_search?: array{
 *         empty_boxes?: scalar|null, // Perhaps one of the three options: show, fade, hide. // Default: "show"
 *         admin_route?: scalar|null, // Change the default route used to generate the link to the object // Default: "show"
 *     },
 *     default_controller?: scalar|null, // Name of the controller class to be used as a default in admin definitions // Default: "sonata.admin.controller.crud"
 *     breadcrumbs?: array{
 *         child_admin_route?: scalar|null, // Change the default route used to generate the link to the parent object, when in a child admin // Default: "show"
 *     },
 *     options?: array{
 *         html5_validate?: bool, // Default: true
 *         sort_admins?: bool, // Auto order groups and admins by label or id // Default: false
 *         confirm_exit?: bool, // Default: true
 *         js_debug?: bool, // Default: false
 *         skin?: "skin-black"|"skin-black-light"|"skin-blue"|"skin-blue-light"|"skin-green"|"skin-green-light"|"skin-purple"|"skin-purple-light"|"skin-red"|"skin-red-light"|"skin-yellow"|"skin-yellow-light", // Default: "skin-black"
 *         use_select2?: bool, // Default: true
 *         use_icheck?: bool, // Default: true
 *         use_bootlint?: bool, // Default: false
 *         use_stickyforms?: bool, // Default: true
 *         pager_links?: int, // Default: null
 *         form_type?: scalar|null, // Default: "standard"
 *         default_admin_route?: scalar|null, // Name of the admin route to be used as a default to generate the link to the object // Default: "show"
 *         default_group?: scalar|null, // Group used for admin services if one isn't provided. // Default: "default"
 *         default_label_catalogue?: scalar|null, // Deprecated: The "default_label_catalogue" node is deprecated, use "default_translation_domain" instead. // Label Catalogue used for admin services if one isn't provided. // Default: "SonataAdminBundle"
 *         default_translation_domain?: scalar|null, // Translation domain used for admin services if one isn't provided. // Default: null
 *         default_icon?: scalar|null, // Icon used for admin services if one isn't provided. // Default: "fas fa-folder"
 *         dropdown_number_groups_per_colums?: int, // Default: 2
 *         logo_content?: "text"|"icon"|"all", // Default: "all"
 *         list_action_button_content?: "text"|"icon"|"all", // Default: "all"
 *         lock_protection?: bool, // Enable locking when editing an object, if the corresponding object manager supports it. // Default: false
 *         mosaic_background?: scalar|null, // Background used in mosaic view // Default: "bundles/sonataadmin/images/default_mosaic_image.png"
 *     },
 *     dashboard?: array{
 *         groups?: array<string, array{ // Default: []
 *             label?: scalar|null,
 *             translation_domain?: scalar|null,
 *             label_catalogue?: scalar|null, // Deprecated: The "label_catalogue" node is deprecated, use "translation_domain" instead.
 *             icon?: scalar|null,
 *             on_top?: scalar|null, // Show menu item in side dashboard menu without treeview // Default: false
 *             keep_open?: scalar|null, // Keep menu group always open // Default: false
 *             provider?: scalar|null,
 *             items?: list<array{ // Default: []
 *                 admin?: scalar|null,
 *                 label?: scalar|null,
 *                 route?: scalar|null,
 *                 roles?: list<scalar|null>,
 *                 route_params?: list<scalar|null>,
 *                 route_absolute?: bool, // Whether the generated url should be absolute // Default: false
 *             }>,
 *             item_adds?: list<scalar|null>,
 *             roles?: list<scalar|null>,
 *         }>,
 *         blocks?: list<array{ // Default: [{"position":"left","settings":[],"type":"sonata.admin.block.admin_list","roles":[]}]
 *             type?: scalar|null,
 *             roles?: list<scalar|null>,
 *             settings?: array<string, mixed>,
 *             position?: scalar|null, // Default: "right"
 *             class?: scalar|null, // Default: "col-md-4"
 *         }>,
 *     },
 *     default_admin_services?: array{
 *         model_manager?: scalar|null, // Default: null
 *         data_source?: scalar|null, // Default: null
 *         field_description_factory?: scalar|null, // Default: null
 *         form_contractor?: scalar|null, // Default: null
 *         show_builder?: scalar|null, // Default: null
 *         list_builder?: scalar|null, // Default: null
 *         datagrid_builder?: scalar|null, // Default: null
 *         translator?: scalar|null, // Default: null
 *         configuration_pool?: scalar|null, // Default: null
 *         route_generator?: scalar|null, // Default: null
 *         security_handler?: scalar|null, // Default: null
 *         menu_factory?: scalar|null, // Default: null
 *         route_builder?: scalar|null, // Default: null
 *         label_translator_strategy?: scalar|null, // Default: null
 *         pager_type?: scalar|null, // Default: null
 *     },
 *     templates?: array{
 *         user_block?: scalar|null, // Default: "@SonataAdmin/Core/user_block.html.twig"
 *         add_block?: scalar|null, // Default: "@SonataAdmin/Core/add_block.html.twig"
 *         layout?: scalar|null, // Default: "@SonataAdmin/standard_layout.html.twig"
 *         ajax?: scalar|null, // Default: "@SonataAdmin/ajax_layout.html.twig"
 *         dashboard?: scalar|null, // Default: "@SonataAdmin/Core/dashboard.html.twig"
 *         search?: scalar|null, // Default: "@SonataAdmin/Core/search.html.twig"
 *         list?: scalar|null, // Default: "@SonataAdmin/CRUD/list.html.twig"
 *         filter?: scalar|null, // Default: "@SonataAdmin/Form/filter_admin_fields.html.twig"
 *         show?: scalar|null, // Default: "@SonataAdmin/CRUD/show.html.twig"
 *         show_compare?: scalar|null, // Default: "@SonataAdmin/CRUD/show_compare.html.twig"
 *         edit?: scalar|null, // Default: "@SonataAdmin/CRUD/edit.html.twig"
 *         preview?: scalar|null, // Default: "@SonataAdmin/CRUD/preview.html.twig"
 *         history?: scalar|null, // Default: "@SonataAdmin/CRUD/history.html.twig"
 *         acl?: scalar|null, // Default: "@SonataAdmin/CRUD/acl.html.twig"
 *         history_revision_timestamp?: scalar|null, // Default: "@SonataAdmin/CRUD/history_revision_timestamp.html.twig"
 *         action?: scalar|null, // Default: "@SonataAdmin/CRUD/action.html.twig"
 *         select?: scalar|null, // Default: "@SonataAdmin/CRUD/list__select.html.twig"
 *         list_block?: scalar|null, // Default: "@SonataAdmin/Block/block_admin_list.html.twig"
 *         search_result_block?: scalar|null, // Default: "@SonataAdmin/Block/block_search_result.html.twig"
 *         short_object_description?: scalar|null, // Default: "@SonataAdmin/Helper/short-object-description.html.twig"
 *         delete?: scalar|null, // Default: "@SonataAdmin/CRUD/delete.html.twig"
 *         batch?: scalar|null, // Default: "@SonataAdmin/CRUD/list__batch.html.twig"
 *         batch_confirmation?: scalar|null, // Default: "@SonataAdmin/CRUD/batch_confirmation.html.twig"
 *         inner_list_row?: scalar|null, // Default: "@SonataAdmin/CRUD/list_inner_row.html.twig"
 *         outer_list_rows_mosaic?: scalar|null, // Default: "@SonataAdmin/CRUD/list_outer_rows_mosaic.html.twig"
 *         outer_list_rows_list?: scalar|null, // Default: "@SonataAdmin/CRUD/list_outer_rows_list.html.twig"
 *         outer_list_rows_tree?: scalar|null, // Default: "@SonataAdmin/CRUD/list_outer_rows_tree.html.twig"
 *         base_list_field?: scalar|null, // Default: "@SonataAdmin/CRUD/base_list_field.html.twig"
 *         pager_links?: scalar|null, // Default: "@SonataAdmin/Pager/links.html.twig"
 *         pager_results?: scalar|null, // Default: "@SonataAdmin/Pager/results.html.twig"
 *         tab_menu_template?: scalar|null, // Default: "@SonataAdmin/Core/tab_menu_template.html.twig"
 *         knp_menu_template?: scalar|null, // Default: "@SonataAdmin/Menu/sonata_menu.html.twig"
 *         action_create?: scalar|null, // Default: "@SonataAdmin/CRUD/dashboard__action_create.html.twig"
 *         button_acl?: scalar|null, // Default: "@SonataAdmin/Button/acl_button.html.twig"
 *         button_create?: scalar|null, // Default: "@SonataAdmin/Button/create_button.html.twig"
 *         button_edit?: scalar|null, // Default: "@SonataAdmin/Button/edit_button.html.twig"
 *         button_history?: scalar|null, // Default: "@SonataAdmin/Button/history_button.html.twig"
 *         button_list?: scalar|null, // Default: "@SonataAdmin/Button/list_button.html.twig"
 *         button_show?: scalar|null, // Default: "@SonataAdmin/Button/show_button.html.twig"
 *         form_theme?: list<scalar|null>,
 *         filter_theme?: list<scalar|null>,
 *     },
 *     assets?: array{
 *         stylesheets?: list<array{ // Default: [{"path":"bundles/sonataadmin/app.css","package_name":"sonata_admin"},{"path":"bundles/sonataform/app.css","package_name":"sonata_admin"}]
 *             path: scalar|null,
 *             package_name?: scalar|null, // Default: "sonata_admin"
 *         }>,
 *         extra_stylesheets?: list<array{ // Default: []
 *             path: scalar|null,
 *             package_name?: scalar|null, // Default: "sonata_admin"
 *         }>,
 *         remove_stylesheets?: list<array{ // Default: []
 *             path: scalar|null,
 *             package_name?: scalar|null, // Default: "sonata_admin"
 *         }>,
 *         javascripts?: list<array{ // Default: [{"path":"bundles/sonataadmin/app.js","package_name":"sonata_admin"},{"path":"bundles/sonataform/app.js","package_name":"sonata_admin"}]
 *             path: scalar|null,
 *             package_name?: scalar|null, // Default: "sonata_admin"
 *         }>,
 *         extra_javascripts?: list<array{ // Default: []
 *             path: scalar|null,
 *             package_name?: scalar|null, // Default: "sonata_admin"
 *         }>,
 *         remove_javascripts?: list<array{ // Default: []
 *             path: scalar|null,
 *             package_name?: scalar|null, // Default: "sonata_admin"
 *         }>,
 *     },
 *     extensions?: array<string, array{ // Default: []
 *         global?: bool, // Default: false
 *         admins?: list<scalar|null>,
 *         excludes?: list<scalar|null>,
 *         implements?: list<scalar|null>,
 *         extends?: list<scalar|null>,
 *         instanceof?: list<scalar|null>,
 *         uses?: list<scalar|null>,
 *         admin_implements?: list<scalar|null>,
 *         admin_extends?: list<scalar|null>,
 *         admin_instanceof?: list<scalar|null>,
 *         admin_uses?: list<scalar|null>,
 *         priority?: int, // Positive or negative integer. The higher the priority, the earlier it’s executed. // Default: 0
 *     }>,
 *     persist_filters?: scalar|null, // Default: false
 *     filter_persister?: scalar|null, // Default: "sonata.admin.filter_persister.session"
 *     show_mosaic_button?: bool, // Show mosaic button on all admin screens // Default: true
 * }
 * @psalm-type SonataDoctrineOrmAdminConfig = array{
 *     entity_manager?: scalar|null, // Default: null
 *     audit?: array{
 *         force?: bool, // Default: true
 *     },
 *     templates?: array{
 *         types?: array{
 *             list?: array<string, scalar|null>,
 *             show?: array<string, scalar|null>,
 *         },
 *     },
 * }
 * @psalm-type SonataClassificationConfig = array{
 *     class?: array{
 *         tag?: scalar|null, // Default: "Application\\Sonata\\ClassificationBundle\\Entity\\Tag"
 *         category?: scalar|null, // Default: "Application\\Sonata\\ClassificationBundle\\Entity\\Category"
 *         collection?: scalar|null, // Default: "Application\\Sonata\\ClassificationBundle\\Entity\\Collection"
 *         context?: scalar|null, // Default: "Application\\Sonata\\ClassificationBundle\\Entity\\Context"
 *     },
 *     admin?: array{
 *         category?: array{
 *             class?: scalar|null, // Default: "Sonata\\ClassificationBundle\\Admin\\CategoryAdmin"
 *             controller?: scalar|null, // Default: "sonata.classification.controller.category_admin"
 *             translation?: scalar|null, // Default: "SonataClassificationBundle"
 *         },
 *         tag?: array{
 *             class?: scalar|null, // Default: "Sonata\\ClassificationBundle\\Admin\\TagAdmin"
 *             controller?: scalar|null, // Default: "%sonata.admin.configuration.default_controller%"
 *             translation?: scalar|null, // Default: "SonataClassificationBundle"
 *         },
 *         collection?: array{
 *             class?: scalar|null, // Default: "Sonata\\ClassificationBundle\\Admin\\CollectionAdmin"
 *             controller?: scalar|null, // Default: "%sonata.admin.configuration.default_controller%"
 *             translation?: scalar|null, // Default: "SonataClassificationBundle"
 *         },
 *         context?: array{
 *             class?: scalar|null, // Default: "Sonata\\ClassificationBundle\\Admin\\ContextAdmin"
 *             controller?: scalar|null, // Default: "%sonata.admin.configuration.default_controller%"
 *             translation?: scalar|null, // Default: "SonataClassificationBundle"
 *         },
 *     },
 * }
 * @psalm-type SonataFormatterConfig = array{
 *     default_formatter: scalar|null,
 *     formatters?: array<string, array{ // Default: []
 *         service: scalar|null,
 *         extensions?: list<scalar|null>,
 *     }>,
 * }
 * @psalm-type SonataMediaConfig = array{
 *     db_driver?: scalar|null, // Choose persistence mechanism driver from the following list: "doctrine_orm", "doctrine_mongodb" // Default: "no_driver"
 *     default_context: scalar|null,
 *     force_disable_category?: bool, // true IF you really want to disable the relation with category // Default: false
 *     admin_format?: array{ // Configures the thumbnail preview for the admin
 *         width?: scalar|null, // Default: 200
 *         height?: scalar|null, // Default: null
 *         quality?: scalar|null, // Default: 90
 *         format?: scalar|null, // Default: "jpg"
 *         constraint?: scalar|null, // Default: true
 *         resizer?: scalar|null, // Default: null
 *         resizer_options?: array<string, scalar|null>,
 *     },
 *     contexts?: array<string, array{ // Default: []
 *         download?: array{
 *             strategy?: scalar|null, // Default: "sonata.media.security.superadmin_strategy"
 *             mode?: scalar|null, // Default: "http"
 *         },
 *         providers?: list<scalar|null>,
 *         formats?: array<string, array{ // Default: []
 *             width?: int, // Default: null
 *             height?: int, // Default: null
 *             quality?: int, // Default: 80
 *             format?: scalar|null, // Default: "jpg"
 *             constraint?: bool, // Default: true
 *             resizer?: scalar|null, // Default: null
 *             resizer_options?: array<string, scalar|null>,
 *         }>,
 *     }>,
 *     cdn?: array{
 *         server?: array{
 *             path?: scalar|null, // Default: "/uploads/media"
 *         },
 *         cloudfront?: array{
 *             path: scalar|null, // e.g. http://xxxxxxxxxxxxxx.cloudfront.net/uploads/media
 *             distribution_id: scalar|null,
 *             key: scalar|null,
 *             secret: scalar|null,
 *             region: scalar|null,
 *             version: scalar|null,
 *         },
 *         fallback?: array{
 *             primary: scalar|null,
 *             fallback: scalar|null,
 *         },
 *     },
 *     filesystem?: array{
 *         local?: array{
 *             directory?: scalar|null, // Default: "%kernel.project_dir%/web/uploads/media"
 *             create?: scalar|null, // Default: false
 *         },
 *         ftp?: array{
 *             directory: scalar|null,
 *             host: scalar|null,
 *             username: scalar|null,
 *             password: scalar|null,
 *             port?: scalar|null, // Default: 21
 *             passive?: scalar|null, // Default: false
 *             create?: scalar|null, // Default: false
 *             mode?: scalar|null, // Default: false
 *         },
 *         s3?: array{
 *             directory?: scalar|null, // Default: ""
 *             bucket: scalar|null,
 *             accessKey: scalar|null,
 *             secretKey: scalar|null,
 *             create?: scalar|null, // Default: false
 *             storage?: scalar|null, // Default: "standard"
 *             cache_control?: scalar|null, // Default: ""
 *             acl?: scalar|null, // Default: "public"
 *             encryption?: scalar|null, // Default: ""
 *             region?: scalar|null, // Default: "s3.amazonaws.com"
 *             endpoint?: scalar|null, // Default: null
 *             version?: scalar|null, // Using "latest" in a production application is not recommended because pulling in a new minor version of the SDK that includes an API update could break your production application. See https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_configuration.html#cfg-version. // Default: "latest"
 *             meta?: array<string, scalar|null>,
 *             async?: bool, // Default: false
 *         },
 *         azure?: array{
 *             container_name: scalar|null,
 *             connection_string: scalar|null,
 *             create_container?: scalar|null, // Default: false
 *             cache_control?: scalar|null, // Default: ""
 *             meta?: array<string, scalar|null>,
 *         },
 *         replicate?: array{
 *             primary: scalar|null,
 *             secondary: scalar|null,
 *         },
 *     },
 *     providers?: array{
 *         file?: array{
 *             service?: scalar|null, // Default: "sonata.media.provider.file"
 *             resizer?: scalar|null, // Default: null
 *             filesystem?: scalar|null, // Default: "sonata.media.filesystem.local"
 *             cdn?: scalar|null, // Default: "sonata.media.cdn.server"
 *             generator?: scalar|null, // Default: "sonata.media.generator.default"
 *             thumbnail?: scalar|null, // Default: "sonata.media.thumbnail.file"
 *             allowed_extensions?: list<scalar|null>,
 *             allowed_mime_types?: list<scalar|null>,
 *         },
 *         image?: array{
 *             service?: scalar|null, // Default: "sonata.media.provider.image"
 *             resizer?: scalar|null, // Default: "sonata.media.resizer.default"
 *             filesystem?: scalar|null, // Default: "sonata.media.filesystem.local"
 *             cdn?: scalar|null, // Default: "sonata.media.cdn.server"
 *             generator?: scalar|null, // Default: "sonata.media.generator.default"
 *             thumbnail?: scalar|null, // Default: "sonata.media.thumbnail.format"
 *             adapter?: scalar|null, // Default: "sonata.media.adapter.image.default"
 *             allowed_extensions?: list<scalar|null>,
 *             allowed_mime_types?: list<scalar|null>,
 *         },
 *         youtube?: array{
 *             service?: scalar|null, // Default: "sonata.media.provider.youtube"
 *             resizer?: scalar|null, // Default: "sonata.media.resizer.default"
 *             filesystem?: scalar|null, // Default: "sonata.media.filesystem.local"
 *             cdn?: scalar|null, // Default: "sonata.media.cdn.server"
 *             generator?: scalar|null, // Default: "sonata.media.generator.default"
 *             thumbnail?: scalar|null, // Default: "sonata.media.thumbnail.format"
 *             html5?: scalar|null, // Default: false
 *         },
 *         dailymotion?: array{
 *             service?: scalar|null, // Default: "sonata.media.provider.dailymotion"
 *             resizer?: scalar|null, // Default: "sonata.media.resizer.default"
 *             filesystem?: scalar|null, // Default: "sonata.media.filesystem.local"
 *             cdn?: scalar|null, // Default: "sonata.media.cdn.server"
 *             generator?: scalar|null, // Default: "sonata.media.generator.default"
 *             thumbnail?: scalar|null, // Default: "sonata.media.thumbnail.format"
 *         },
 *         vimeo?: array{
 *             service?: scalar|null, // Default: "sonata.media.provider.vimeo"
 *             resizer?: scalar|null, // Default: "sonata.media.resizer.default"
 *             filesystem?: scalar|null, // Default: "sonata.media.filesystem.local"
 *             cdn?: scalar|null, // Default: "sonata.media.cdn.server"
 *             generator?: scalar|null, // Default: "sonata.media.generator.default"
 *             thumbnail?: scalar|null, // Default: "sonata.media.thumbnail.format"
 *         },
 *     },
 *     class?: array{
 *         media?: scalar|null, // Default: "App\\Entity\\SonataMediaMedia"
 *         gallery?: scalar|null, // Default: "App\\Entity\\SonataMediaGallery"
 *         gallery_item?: scalar|null, // Default: "App\\Entity\\SonataMediaGalleryItem"
 *         category?: scalar|null, // Default: "App\\Entity\\SonataClassificationCategory"
 *     },
 *     http?: array{
 *         client?: scalar|null, // Alias of the http client. // Default: "sonata.media.http.base_client"
 *         message_factory?: scalar|null, // Alias of the message factory. // Default: "sonata.media.http.base_message_factory"
 *     },
 *     resizer?: array{
 *         simple?: array{
 *             mode?: int, // Default: 1
 *         },
 *         square?: array{
 *             mode?: int, // Default: 1
 *         },
 *     },
 *     resizers?: array{
 *         default?: scalar|null, // Default: "sonata.media.resizer.simple"
 *     },
 *     adapters?: array{
 *         default?: scalar|null, // Default: "sonata.media.adapter.image.gd"
 *     },
 *     messenger?: bool|array{
 *         enabled?: bool, // Default: false
 *         generate_thumbnails_bus?: scalar|null, // Default: "messenger.default_bus"
 *     },
 * }
 * @psalm-type SonataSeoConfig = array{
 *     encoding?: scalar|null, // Default: "UTF-8"
 *     page?: array{
 *         default?: scalar|null, // Default: "sonata.seo.page.default"
 *         head?: array<string, scalar|null>,
 *         metas?: array<string, array<string, scalar|null>>,
 *         separator?: scalar|null, // Default: " - "
 *         title?: scalar|null, // Default: ""
 *         title_prefix?: scalar|null, // Default: null
 *         title_suffix?: scalar|null, // Default: null
 *     },
 *     sitemap?: array{
 *         doctrine_orm?: list<mixed>,
 *         services?: list<mixed>,
 *     },
 * }
 * @psalm-type SonataPageConfig = array{
 *     skip_redirection?: scalar|null, // To skip asking Editor to redirect // Default: false
 *     hide_disabled_blocks?: scalar|null, // Default: false
 *     use_streamed_response?: scalar|null, // Set the value to false in debug mode or if the reverse proxy does not handle streamed response // Default: false
 *     multisite: scalar|null, // For more information, see https://docs.sonata-project.org/projects/SonataPageBundle/en/4.x/reference/multisite/
 *     router_auto_register?: array{ // Automatically add 'sonata.page.router' service to the index of 'cmf_routing.router' chain router Examples: enabled: true Enable auto-registration priority: 150 The priority
 *         enabled?: bool, // Default: false
 *         priority?: int, // Default: 150
 *     },
 *     ignore_route_patterns?: list<scalar|null>,
 *     ignore_routes?: list<scalar|null>,
 *     ignore_uri_patterns?: list<scalar|null>,
 *     default_page_service?: scalar|null, // Default: "sonata.page.service.default"
 *     default_template: scalar|null, // Template key from templates section, used as default for pages
 *     templates: array<string, array{ // Default: []
 *         name?: scalar|null,
 *         path?: scalar|null,
 *         inherits_containers?: scalar|null,
 *         containers?: array<string, array{ // Default: []
 *             name?: scalar|null,
 *             shared?: bool, // Default: false
 *             type?: scalar|null, // Default: 1
 *             blocks?: list<scalar|null>,
 *         }>,
 *         matrix?: array{
 *             layout: scalar|null,
 *             mapping: list<scalar|null>,
 *         },
 *     }>,
 *     templates_admin?: array{
 *         list?: scalar|null, // Default: "@SonataPage/PageAdmin/list.html.twig"
 *         tree?: scalar|null, // Default: "@SonataPage/PageAdmin/tree.html.twig"
 *         compose?: scalar|null, // Default: "@SonataPage/PageAdmin/compose.html.twig"
 *         compose_container_show?: scalar|null, // Default: "@SonataPage/PageAdmin/compose_container_show.html.twig"
 *         select_site?: scalar|null, // Default: "@SonataPage/PageAdmin/select_site.html.twig"
 *     },
 *     page_defaults?: array<string, array{ // Default: []
 *         decorate?: bool, // Default: true
 *         enabled?: bool, // Default: true
 *     }>,
 *     catch_exceptions?: array<string, mixed>,
 *     class?: array{
 *         page?: scalar|null, // Default: "App\\Entity\\SonataPagePage"
 *         snapshot?: scalar|null, // Default: "App\\Entity\\SonataPageSnapshot"
 *         block?: scalar|null, // Default: "App\\Entity\\SonataPageBlock"
 *         site?: scalar|null, // Default: "App\\Entity\\SonataPageSite"
 *     },
 *     direct_publication?: bool, // Generates a snapshot when a page is saved from the admin. You can use %kernel.debug%, if you want to publish in dev mode, but not in prod. // Default: false
 * }
 * @psalm-type StofDoctrineExtensionsConfig = array{
 *     orm?: array<string, array{ // Default: []
 *         translatable?: scalar|null, // Default: false
 *         timestampable?: scalar|null, // Default: false
 *         blameable?: scalar|null, // Default: false
 *         sluggable?: scalar|null, // Default: false
 *         tree?: scalar|null, // Default: false
 *         loggable?: scalar|null, // Default: false
 *         ip_traceable?: scalar|null, // Default: false
 *         sortable?: scalar|null, // Default: false
 *         softdeleteable?: scalar|null, // Default: false
 *         uploadable?: scalar|null, // Default: false
 *         reference_integrity?: scalar|null, // Default: false
 *     }>,
 *     mongodb?: array<string, array{ // Default: []
 *         translatable?: scalar|null, // Default: false
 *         timestampable?: scalar|null, // Default: false
 *         blameable?: scalar|null, // Default: false
 *         sluggable?: scalar|null, // Default: false
 *         tree?: scalar|null, // Default: false
 *         loggable?: scalar|null, // Default: false
 *         ip_traceable?: scalar|null, // Default: false
 *         sortable?: scalar|null, // Default: false
 *         softdeleteable?: scalar|null, // Default: false
 *         uploadable?: scalar|null, // Default: false
 *         reference_integrity?: scalar|null, // Default: false
 *     }>,
 *     class?: array{
 *         translatable?: scalar|null, // Default: "Gedmo\\Translatable\\TranslatableListener"
 *         timestampable?: scalar|null, // Default: "Gedmo\\Timestampable\\TimestampableListener"
 *         blameable?: scalar|null, // Default: "Gedmo\\Blameable\\BlameableListener"
 *         sluggable?: scalar|null, // Default: "Gedmo\\Sluggable\\SluggableListener"
 *         tree?: scalar|null, // Default: "Gedmo\\Tree\\TreeListener"
 *         loggable?: scalar|null, // Default: "Gedmo\\Loggable\\LoggableListener"
 *         sortable?: scalar|null, // Default: "Gedmo\\Sortable\\SortableListener"
 *         softdeleteable?: scalar|null, // Default: "Gedmo\\SoftDeleteable\\SoftDeleteableListener"
 *         uploadable?: scalar|null, // Default: "Gedmo\\Uploadable\\UploadableListener"
 *         reference_integrity?: scalar|null, // Default: "Gedmo\\ReferenceIntegrity\\ReferenceIntegrityListener"
 *     },
 *     softdeleteable?: array{
 *         handle_post_flush_event?: bool, // Default: false
 *     },
 *     uploadable?: array{
 *         default_file_path?: scalar|null, // Default: null
 *         mime_type_guesser_class?: scalar|null, // Default: "Stof\\DoctrineExtensionsBundle\\Uploadable\\MimeTypeGuesserAdapter"
 *         default_file_info_class?: scalar|null, // Default: "Stof\\DoctrineExtensionsBundle\\Uploadable\\UploadedFileInfo"
 *         validate_writable_directory?: bool, // Default: true
 *     },
 *     default_locale?: scalar|null, // Default: "en"
 *     translation_fallback?: bool, // Default: false
 *     persist_default_translation?: bool, // Default: false
 *     skip_translation_on_load?: bool, // Default: false
 *     metadata_cache_pool?: scalar|null, // Default: null
 * }
 * @psalm-type WebProfilerConfig = array{
 *     toolbar?: bool|array{ // Profiler toolbar configuration
 *         enabled?: bool, // Default: false
 *         ajax_replace?: bool, // Replace toolbar on AJAX requests // Default: false
 *     },
 *     intercept_redirects?: bool, // Default: false
 *     excluded_ajax_paths?: scalar|null, // Default: "^/((index|app(_[\\w]+)?)\\.php/)?_wdt"
 * }
 * @psalm-type DoctrineMigrationsConfig = array{
 *     enable_service_migrations?: bool, // Whether to enable fetching migrations from the service container. // Default: false
 *     migrations_paths?: array<string, scalar|null>,
 *     services?: array<string, scalar|null>,
 *     factories?: array<string, scalar|null>,
 *     storage?: array{ // Storage to use for migration status metadata.
 *         table_storage?: array{ // The default metadata storage, implemented as a table in the database.
 *             table_name?: scalar|null, // Default: null
 *             version_column_name?: scalar|null, // Default: null
 *             version_column_length?: scalar|null, // Default: null
 *             executed_at_column_name?: scalar|null, // Default: null
 *             execution_time_column_name?: scalar|null, // Default: null
 *         },
 *     },
 *     migrations?: list<scalar|null>,
 *     connection?: scalar|null, // Connection name to use for the migrations database. // Default: null
 *     em?: scalar|null, // Entity manager name to use for the migrations database (available when doctrine/orm is installed). // Default: null
 *     all_or_nothing?: scalar|null, // Run all migrations in a transaction. // Default: false
 *     check_database_platform?: scalar|null, // Adds an extra check in the generated migrations to allow execution only on the same platform as they were initially generated on. // Default: true
 *     custom_template?: scalar|null, // Custom template path for generated migration classes. // Default: null
 *     organize_migrations?: scalar|null, // Organize migrations mode. Possible values are: "BY_YEAR", "BY_YEAR_AND_MONTH", false // Default: false
 *     enable_profiler?: bool, // Whether or not to enable the profiler collector to calculate and visualize migration status. This adds some queries overhead. // Default: false
 *     transactional?: bool, // Whether or not to wrap migrations in a single transaction. // Default: true
 * }
 * @psalm-type SonataTwigConfig = array{
 *     form_type?: "standard"|"horizontal", // Style used in the forms, some of the widgets need to be wrapped in a special div element depending on this style. // Default: "standard"
 *     flashmessage?: array<string, array{ // Default: []
 *         css_class?: scalar|null,
 *         types?: list<scalar|null>,
 *     }>,
 * }
 * @psalm-type SonataFormConfig = array{
 *     form_type?: scalar|null, // Must be one of standard, horizontal // Default: "standard"
 * }
 * @psalm-type DebugConfig = array{
 *     max_items?: int, // Max number of displayed items past the first level, -1 means no limit. // Default: 2500
 *     min_depth?: int, // Minimum tree depth to clone all the items, 1 is default. // Default: 1
 *     max_string_length?: int, // Max length of displayed strings, -1 means no limit. // Default: -1
 *     dump_destination?: scalar|null, // A stream URL where dumps should be written to. // Default: null
 *     theme?: "dark"|"light", // Changes the color of the dump() output when rendered directly on the templating. "dark" (default) or "light". // Default: "dark"
 * }
 * @psalm-type SymfonycastsResetPasswordConfig = array{
 *     request_password_repository: scalar|null, // A class that implements ResetPasswordRequestRepositoryInterface - usually your ResetPasswordRequestRepository.
 *     lifetime?: int, // The length of time in seconds that a password reset request is valid for after it is created. // Default: 3600
 *     throttle_limit?: int, // Another password reset cannot be made faster than this throttle time in seconds. // Default: 3600
 *     enable_garbage_collection?: bool, // Enable/Disable automatic garbage collection. // Default: true
 * }
 * @psalm-type SimpleThingsEntityAuditConfig = array{
 *     connection?: scalar|null, // Default: "default"
 *     entity_manager?: scalar|null, // Default: "default"
 *     audited_entities?: list<scalar|null>,
 *     global_ignore_columns?: list<scalar|null>,
 *     table_prefix?: scalar|null, // Default: ""
 *     table_suffix?: scalar|null, // Default: "_audit"
 *     revision_field_name?: scalar|null, // Default: "rev"
 *     revision_type_field_name?: scalar|null, // Default: "revtype"
 *     revision_table_name?: scalar|null, // Default: "revisions"
 *     disable_foreign_keys?: scalar|null, // Default: false
 *     revision_id_field_type?: scalar|null, // Default: "integer"
 *     service?: array{
 *         username_callable?: scalar|null, // Default: "simplethings_entityaudit.username_callable.token_storage"
 *     },
 * }
 * @psalm-type SonataExporterConfig = array{
 *     exporter?: array{
 *         default_writers?: list<scalar|null>,
 *     },
 *     writers?: array{
 *         csv?: array{
 *             filename?: scalar|null, // path to the output file // Default: "php://output"
 *             delimiter?: scalar|null, // delimits csv values // Default: ","
 *             enclosure?: scalar|null, // will be used when a value contains the delimiter // Default: "\""
 *             escape?: scalar|null, // will be used when a value contains the enclosure // Default: "\\"
 *             show_headers?: bool, // add column names as the first line // Default: true
 *             with_bom?: bool, // include the byte order mark // Default: false
 *         },
 *         json?: array{
 *             filename?: scalar|null, // path to the output file // Default: "php://output"
 *         },
 *         xls?: array{
 *             filename?: scalar|null, // path to the output file // Default: "php://output"
 *             show_headers?: bool, // add column names as the first line // Default: true
 *         },
 *         xlsx?: array{
 *             filename?: scalar|null, // path to the output file // Default: "php://output"
 *             show_headers?: bool, // add column names as the first line // Default: true
 *             show_filters?: bool, // add filters in the first line // Default: true
 *         },
 *         xml?: array{
 *             filename?: scalar|null, // path to the output file // Default: "php://output"
 *             show_headers?: bool, // add column names as the first line // Default: true
 *             main_element?: scalar|null, // name of the wrapping element // Default: "datas"
 *             child_element?: scalar|null, // name of elements corresponding to rows // Default: "data"
 *         },
 *     },
 * }
 * @psalm-type TwigExtraConfig = array{
 *     cache?: bool|array{
 *         enabled?: bool, // Default: false
 *     },
 *     html?: bool|array{
 *         enabled?: bool, // Default: false
 *     },
 *     markdown?: bool|array{
 *         enabled?: bool, // Default: true
 *     },
 *     intl?: bool|array{
 *         enabled?: bool, // Default: true
 *     },
 *     cssinliner?: bool|array{
 *         enabled?: bool, // Default: false
 *     },
 *     inky?: bool|array{
 *         enabled?: bool, // Default: false
 *     },
 *     string?: bool|array{
 *         enabled?: bool, // Default: true
 *     },
 *     commonmark?: array{
 *         renderer?: array{ // Array of options for rendering HTML.
 *             block_separator?: scalar|null,
 *             inner_separator?: scalar|null,
 *             soft_break?: scalar|null,
 *         },
 *         html_input?: "strip"|"allow"|"escape", // How to handle HTML input.
 *         allow_unsafe_links?: bool, // Remove risky link and image URLs by setting this to false. // Default: true
 *         max_nesting_level?: int, // The maximum nesting level for blocks. // Default: 9223372036854775807
 *         max_delimiters_per_line?: int, // The maximum number of strong/emphasis delimiters per line. // Default: 9223372036854775807
 *         slug_normalizer?: array{ // Array of options for configuring how URL-safe slugs are created.
 *             instance?: mixed,
 *             max_length?: int, // Default: 255
 *             unique?: mixed,
 *         },
 *         commonmark?: array{ // Array of options for configuring the CommonMark core extension.
 *             enable_em?: bool, // Default: true
 *             enable_strong?: bool, // Default: true
 *             use_asterisk?: bool, // Default: true
 *             use_underscore?: bool, // Default: true
 *             unordered_list_markers?: list<scalar|null>,
 *         },
 *         ...<mixed>
 *     },
 * }
 * @psalm-type TurboConfig = array{
 *     broadcast?: bool|array{
 *         enabled?: bool, // Default: true
 *         entity_template_prefixes?: list<scalar|null>,
 *         doctrine_orm?: bool|array{ // Enable the Doctrine ORM integration
 *             enabled?: bool, // Default: true
 *         },
 *     },
 *     default_transport?: scalar|null, // Default: "default"
 * }
 * @psalm-type LeagueOauth2ServerConfig = array{
 *     authorization_server: array{
 *         private_key: scalar|null, // Full path to the private key file. How to generate a private key: https://oauth2.thephpleague.com/installation/#generating-public-and-private-keys
 *         private_key_passphrase?: scalar|null, // Passphrase of the private key, if any // Default: null
 *         encryption_key: scalar|null, // The plain string or the ascii safe string used to create a Defuse\Crypto\Key to be used as an encryption key. How to generate an encryption key: https://oauth2.thephpleague.com/installation/#string-password
 *         encryption_key_type?: scalar|null, // The type of value of 'encryption_key' Should be either 'plain' or 'defuse' // Default: "plain"
 *         access_token_ttl?: scalar|null, // How long the issued access token should be valid for. The value should be a valid interval: http://php.net/manual/en/dateinterval.construct.php#refsect1-dateinterval.construct-parameters // Default: "PT1H"
 *         refresh_token_ttl?: scalar|null, // How long the issued refresh token should be valid for. The value should be a valid interval: http://php.net/manual/en/dateinterval.construct.php#refsect1-dateinterval.construct-parameters // Default: "P1M"
 *         auth_code_ttl?: scalar|null, // How long the issued auth code should be valid for. The value should be a valid interval: http://php.net/manual/en/dateinterval.construct.php#refsect1-dateinterval.construct-parameters // Default: "PT10M"
 *         enable_client_credentials_grant?: bool, // Whether to enable the client credentials grant // Default: true
 *         enable_password_grant?: bool, // Whether to enable the password grant // Default: true
 *         enable_refresh_token_grant?: bool, // Whether to enable the refresh token grant // Default: true
 *         enable_auth_code_grant?: bool, // Whether to enable the authorization code grant // Default: true
 *         require_code_challenge_for_public_clients?: bool, // Whether to require code challenge for public clients for the auth code grant // Default: true
 *         enable_implicit_grant?: bool, // Whether to enable the implicit grant // Default: true
 *         persist_access_token?: bool, // Whether to enable access token saving to persistence layer // Default: true
 *         response_type_class?: scalar|null, // Define a custom ResponseType // Default: null
 *         revoke_refresh_tokens?: bool, // Whether to revoke refresh tokens after they were used for all grant types // Default: true
 *     },
 *     resource_server: array{
 *         public_key: scalar|null, // Full path to the public key file How to generate a public key: https://oauth2.thephpleague.com/installation/#generating-public-and-private-keys
 *         jwt_leeway?: scalar|null, // The leeway in seconds to allow for clock skew in JWT verification. Default PT0S (no leeway). // Default: null
 *     },
 *     scopes: array{
 *         available: list<scalar|null>,
 *         default: list<scalar|null>,
 *     },
 *     persistence: array{ // Configures different persistence methods that can be used by the bundle for saving client and token data. Only one persistence method can be configured at a time.
 *         doctrine?: array{
 *             entity_manager?: scalar|null, // Name of the entity manager that you wish to use for managing clients and tokens. // Default: "default"
 *             table_prefix?: scalar|null, // Table name prefix. // Default: "oauth2_"
 *         },
 *         in_memory?: scalar|null,
 *         custom?: array{
 *             access_token_manager: scalar|null, // Service id of the custom access token manager
 *             authorization_code_manager: scalar|null, // Service id of the custom authorization code manager
 *             client_manager: scalar|null, // Service id of the custom client manager
 *             refresh_token_manager: scalar|null, // Service id of the custom refresh token manager
 *             credentials_revoker: scalar|null, // Service id of the custom credentials revoker
 *         },
 *     },
 *     client?: array{
 *         classname?: scalar|null, // Set a custom client class. Must be a League\Bundle\OAuth2ServerBundle\Model\AbstractClient // Default: "League\\Bundle\\OAuth2ServerBundle\\Model\\Client"
 *     },
 *     role_prefix?: scalar|null, // Set a custom prefix that replaces the default 'ROLE_OAUTH2_' role prefix // Default: "ROLE_OAUTH2_"
 * }
 * @psalm-type StimulusConfig = array{
 *     controller_paths?: list<scalar|null>,
 *     controllers_json?: scalar|null, // Default: "%kernel.project_dir%/assets/controllers.json"
 * }
 * @psalm-type FptStripeConfig = array{
 *     credentials?: array{
 *         publishable_key: scalar|null,
 *         secret_key: scalar|null,
 *     },
 *     webhook?: array{
 *         check_signature?: bool, // Default: true
 *         signature_key?: scalar|null,
 *     },
 * }
 * @psalm-type UxIconsConfig = array{
 *     icon_dir?: scalar|null, // The local directory where icons are stored. // Default: "%kernel.project_dir%/assets/icons"
 *     default_icon_attributes?: mixed, // Default attributes to add to all icons. // Default: {"fill":"currentColor"}
 *     icon_sets?: array<string, array{ // the icon set prefix (e.g. "acme") // Default: []
 *         path?: scalar|null, // The local icon set directory path. (cannot be used with 'alias')
 *         alias?: scalar|null, // The remote icon set identifier. (cannot be used with 'path')
 *         icon_attributes?: list<mixed>,
 *     }>,
 *     aliases?: list<scalar|null>,
 *     iconify?: bool|array{ // Configuration for the remote icon service.
 *         enabled?: bool, // Default: true
 *         on_demand?: bool, // Whether to download icons "on demand". // Default: true
 *         endpoint?: scalar|null, // The endpoint for the Iconify icons API. // Default: "https://api.iconify.design"
 *     },
 *     ignore_not_found?: bool, // Ignore error when an icon is not found. Set to 'true' to fail silently. // Default: false
 * }
 * @psalm-type TwigComponentConfig = array{
 *     defaults?: array<string, string|array{ // Default: ["__deprecated__use_old_naming_behavior"]
 *         template_directory?: scalar|null, // Default: "components"
 *         name_prefix?: scalar|null, // Default: ""
 *     }>,
 *     anonymous_template_directory?: scalar|null, // Defaults to `components`
 *     profiler?: bool, // Enables the profiler for Twig Component (in debug mode) // Default: "%kernel.debug%"
 *     controllers_json?: scalar|null, // Deprecated: The "twig_component.controllers_json" config option is deprecated, and will be removed in 3.0. // Default: null
 * }
 * @psalm-type LiveComponentConfig = array{
 *     secret?: scalar|null, // The secret used to compute fingerprints and checksums // Default: "%kernel.secret%"
 * }
 * @psalm-type UxMapConfig = array{
 *     renderer?: scalar|null, // Default: null
 *     google_maps?: array{
 *         default_map_id?: scalar|null, // Default: null
 *     },
 * }
 * @psalm-type SymfonycastsVerifyEmailConfig = array{
 *     lifetime?: int, // The length of time in seconds that a signed URI is valid for after it is created. // Default: 3600
 * }
 * @psalm-type DamaDoctrineTestConfig = array{
 *     enable_static_connection?: mixed, // Default: true
 *     enable_static_meta_data_cache?: bool, // Default: true
 *     enable_static_query_cache?: bool, // Default: true
 *     connection_keys?: list<mixed>,
 * }
 * @psalm-type ZenstruckFoundryConfig = array{
 *     auto_refresh_proxies?: bool|null, // Deprecated: Since 2.0 auto_refresh_proxies defaults to true and this configuration has no effect. // Whether to auto-refresh proxies by default (https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#auto-refresh) // Default: null
 *     enable_auto_refresh_with_lazy_objects?: bool|null, // Enable auto-refresh using PHP 8.4 lazy objects (cannot be enabled if PHP < 8.4). // Default: null
 *     faker?: array{ // Configure the faker used by your factories.
 *         locale?: scalar|null, // The default locale to use for faker. // Default: null
 *         seed?: scalar|null, // Deprecated: The "faker.seed" configuration is deprecated and will be removed in 3.0. Use environment variable "FOUNDRY_FAKER_SEED" instead. // Random number generator seed to produce the same fake values every run. // Default: null
 *         service?: scalar|null, // Service id for custom faker instance. // Default: null
 *     },
 *     instantiator?: array{ // Configure the default instantiator used by your object factories.
 *         use_constructor?: bool, // Use the constructor to instantiate objects. // Default: true
 *         allow_extra_attributes?: bool, // Whether or not to skip attributes that do not correspond to properties. // Default: false
 *         always_force_properties?: bool, // Whether or not to skip setters and force set object properties (public/private/protected) directly. // Default: false
 *         service?: scalar|null, // Service id of your custom instantiator. // Default: null
 *     },
 *     global_state?: list<scalar|null>,
 *     persistence?: array{
 *         flush_once?: bool, // Flush only once per call of `PersistentObjectFactory::create()` in userland. // Default: false
 *     },
 *     orm?: array{
 *         auto_persist?: bool, // Deprecated: Since 2.4 auto_persist defaults to true and this configuration has no effect. // Automatically persist entities when created. // Default: true
 *         reset?: array{
 *             connections?: list<scalar|null>,
 *             entity_managers?: list<scalar|null>,
 *             mode?: \Zenstruck\Foundry\ORM\ResetDatabase\ResetDatabaseMode::SCHEMA|\Zenstruck\Foundry\ORM\ResetDatabase\ResetDatabaseMode::MIGRATE, // Reset mode to use with ResetDatabase trait // Default: "schema"
 *             migrations?: array{
 *                 configurations?: list<scalar|null>,
 *             },
 *         },
 *     },
 *     mongo?: array{
 *         auto_persist?: bool, // Deprecated: Since 2.4 auto_persist defaults to true and this configuration has no effect. // Automatically persist documents when created. // Default: true
 *         reset?: array{
 *             document_managers?: list<scalar|null>,
 *         },
 *     },
 *     make_factory?: array{
 *         default_namespace?: scalar|null, // Default namespace where factories will be created by maker. // Default: "Factory"
 *         add_hints?: bool, // Add "beginner" hints in the created factory. // Default: true
 *     },
 *     make_story?: array{
 *         default_namespace?: scalar|null, // Default namespace where stories will be created by maker. // Default: "Story"
 *     },
 * }
 * @psalm-type ConfigType = array{
 *     imports?: ImportsConfig,
 *     parameters?: ParametersConfig,
 *     services?: ServicesConfig,
 *     doctrine?: DoctrineConfig,
 *     fos_ck_editor?: FosCkEditorConfig,
 *     knp_menu?: KnpMenuConfig,
 *     knpu_oauth2_client?: KnpuOauth2ClientConfig,
 *     framework?: FrameworkConfig,
 *     twig?: TwigConfig,
 *     security?: SecurityConfig,
 *     monolog?: MonologConfig,
 *     cmf_routing?: CmfRoutingConfig,
 *     sonata_block?: SonataBlockConfig,
 *     sonata_admin?: SonataAdminConfig,
 *     sonata_doctrine_orm_admin?: SonataDoctrineOrmAdminConfig,
 *     sonata_classification?: SonataClassificationConfig,
 *     sonata_formatter?: SonataFormatterConfig,
 *     sonata_media?: SonataMediaConfig,
 *     sonata_seo?: SonataSeoConfig,
 *     sonata_page?: SonataPageConfig,
 *     stof_doctrine_extensions?: StofDoctrineExtensionsConfig,
 *     doctrine_migrations?: DoctrineMigrationsConfig,
 *     sonata_twig?: SonataTwigConfig,
 *     sonata_form?: SonataFormConfig,
 *     symfonycasts_reset_password?: SymfonycastsResetPasswordConfig,
 *     simple_things_entity_audit?: SimpleThingsEntityAuditConfig,
 *     sonata_exporter?: SonataExporterConfig,
 *     twig_extra?: TwigExtraConfig,
 *     turbo?: TurboConfig,
 *     league_oauth2_server?: LeagueOauth2ServerConfig,
 *     stimulus?: StimulusConfig,
 *     fpt_stripe?: FptStripeConfig,
 *     ux_icons?: UxIconsConfig,
 *     twig_component?: TwigComponentConfig,
 *     live_component?: LiveComponentConfig,
 *     ux_map?: UxMapConfig,
 *     symfonycasts_verify_email?: SymfonycastsVerifyEmailConfig,
 *     "when@dev"?: array{
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         doctrine?: DoctrineConfig,
 *         fos_ck_editor?: FosCkEditorConfig,
 *         knp_menu?: KnpMenuConfig,
 *         knpu_oauth2_client?: KnpuOauth2ClientConfig,
 *         framework?: FrameworkConfig,
 *         twig?: TwigConfig,
 *         security?: SecurityConfig,
 *         monolog?: MonologConfig,
 *         maker?: MakerConfig,
 *         cmf_routing?: CmfRoutingConfig,
 *         sonata_block?: SonataBlockConfig,
 *         sonata_admin?: SonataAdminConfig,
 *         sonata_doctrine_orm_admin?: SonataDoctrineOrmAdminConfig,
 *         sonata_classification?: SonataClassificationConfig,
 *         sonata_formatter?: SonataFormatterConfig,
 *         sonata_media?: SonataMediaConfig,
 *         sonata_seo?: SonataSeoConfig,
 *         sonata_page?: SonataPageConfig,
 *         stof_doctrine_extensions?: StofDoctrineExtensionsConfig,
 *         web_profiler?: WebProfilerConfig,
 *         doctrine_migrations?: DoctrineMigrationsConfig,
 *         sonata_twig?: SonataTwigConfig,
 *         sonata_form?: SonataFormConfig,
 *         debug?: DebugConfig,
 *         symfonycasts_reset_password?: SymfonycastsResetPasswordConfig,
 *         simple_things_entity_audit?: SimpleThingsEntityAuditConfig,
 *         sonata_exporter?: SonataExporterConfig,
 *         twig_extra?: TwigExtraConfig,
 *         turbo?: TurboConfig,
 *         league_oauth2_server?: LeagueOauth2ServerConfig,
 *         stimulus?: StimulusConfig,
 *         fpt_stripe?: FptStripeConfig,
 *         ux_icons?: UxIconsConfig,
 *         twig_component?: TwigComponentConfig,
 *         live_component?: LiveComponentConfig,
 *         ux_map?: UxMapConfig,
 *         symfonycasts_verify_email?: SymfonycastsVerifyEmailConfig,
 *         zenstruck_foundry?: ZenstruckFoundryConfig,
 *     },
 *     "when@panther"?: array{
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         doctrine?: DoctrineConfig,
 *         fos_ck_editor?: FosCkEditorConfig,
 *         knp_menu?: KnpMenuConfig,
 *         knpu_oauth2_client?: KnpuOauth2ClientConfig,
 *         framework?: FrameworkConfig,
 *         twig?: TwigConfig,
 *         security?: SecurityConfig,
 *         monolog?: MonologConfig,
 *         cmf_routing?: CmfRoutingConfig,
 *         sonata_block?: SonataBlockConfig,
 *         sonata_admin?: SonataAdminConfig,
 *         sonata_doctrine_orm_admin?: SonataDoctrineOrmAdminConfig,
 *         sonata_classification?: SonataClassificationConfig,
 *         sonata_formatter?: SonataFormatterConfig,
 *         sonata_media?: SonataMediaConfig,
 *         sonata_seo?: SonataSeoConfig,
 *         sonata_page?: SonataPageConfig,
 *         stof_doctrine_extensions?: StofDoctrineExtensionsConfig,
 *         doctrine_migrations?: DoctrineMigrationsConfig,
 *         sonata_twig?: SonataTwigConfig,
 *         sonata_form?: SonataFormConfig,
 *         symfonycasts_reset_password?: SymfonycastsResetPasswordConfig,
 *         simple_things_entity_audit?: SimpleThingsEntityAuditConfig,
 *         sonata_exporter?: SonataExporterConfig,
 *         twig_extra?: TwigExtraConfig,
 *         turbo?: TurboConfig,
 *         league_oauth2_server?: LeagueOauth2ServerConfig,
 *         stimulus?: StimulusConfig,
 *         fpt_stripe?: FptStripeConfig,
 *         ux_icons?: UxIconsConfig,
 *         twig_component?: TwigComponentConfig,
 *         live_component?: LiveComponentConfig,
 *         ux_map?: UxMapConfig,
 *         symfonycasts_verify_email?: SymfonycastsVerifyEmailConfig,
 *         zenstruck_foundry?: ZenstruckFoundryConfig,
 *     },
 *     "when@prod"?: array{
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         doctrine?: DoctrineConfig,
 *         fos_ck_editor?: FosCkEditorConfig,
 *         knp_menu?: KnpMenuConfig,
 *         knpu_oauth2_client?: KnpuOauth2ClientConfig,
 *         framework?: FrameworkConfig,
 *         twig?: TwigConfig,
 *         security?: SecurityConfig,
 *         monolog?: MonologConfig,
 *         cmf_routing?: CmfRoutingConfig,
 *         sonata_block?: SonataBlockConfig,
 *         sonata_admin?: SonataAdminConfig,
 *         sonata_doctrine_orm_admin?: SonataDoctrineOrmAdminConfig,
 *         sonata_classification?: SonataClassificationConfig,
 *         sonata_formatter?: SonataFormatterConfig,
 *         sonata_media?: SonataMediaConfig,
 *         sonata_seo?: SonataSeoConfig,
 *         sonata_page?: SonataPageConfig,
 *         stof_doctrine_extensions?: StofDoctrineExtensionsConfig,
 *         doctrine_migrations?: DoctrineMigrationsConfig,
 *         sonata_twig?: SonataTwigConfig,
 *         sonata_form?: SonataFormConfig,
 *         symfonycasts_reset_password?: SymfonycastsResetPasswordConfig,
 *         simple_things_entity_audit?: SimpleThingsEntityAuditConfig,
 *         sonata_exporter?: SonataExporterConfig,
 *         twig_extra?: TwigExtraConfig,
 *         turbo?: TurboConfig,
 *         league_oauth2_server?: LeagueOauth2ServerConfig,
 *         stimulus?: StimulusConfig,
 *         fpt_stripe?: FptStripeConfig,
 *         ux_icons?: UxIconsConfig,
 *         twig_component?: TwigComponentConfig,
 *         live_component?: LiveComponentConfig,
 *         ux_map?: UxMapConfig,
 *         symfonycasts_verify_email?: SymfonycastsVerifyEmailConfig,
 *     },
 *     "when@test"?: array{
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         doctrine?: DoctrineConfig,
 *         fos_ck_editor?: FosCkEditorConfig,
 *         knp_menu?: KnpMenuConfig,
 *         knpu_oauth2_client?: KnpuOauth2ClientConfig,
 *         framework?: FrameworkConfig,
 *         twig?: TwigConfig,
 *         security?: SecurityConfig,
 *         monolog?: MonologConfig,
 *         cmf_routing?: CmfRoutingConfig,
 *         sonata_block?: SonataBlockConfig,
 *         sonata_admin?: SonataAdminConfig,
 *         sonata_doctrine_orm_admin?: SonataDoctrineOrmAdminConfig,
 *         sonata_classification?: SonataClassificationConfig,
 *         sonata_formatter?: SonataFormatterConfig,
 *         sonata_media?: SonataMediaConfig,
 *         sonata_seo?: SonataSeoConfig,
 *         sonata_page?: SonataPageConfig,
 *         stof_doctrine_extensions?: StofDoctrineExtensionsConfig,
 *         web_profiler?: WebProfilerConfig,
 *         doctrine_migrations?: DoctrineMigrationsConfig,
 *         sonata_twig?: SonataTwigConfig,
 *         sonata_form?: SonataFormConfig,
 *         debug?: DebugConfig,
 *         symfonycasts_reset_password?: SymfonycastsResetPasswordConfig,
 *         simple_things_entity_audit?: SimpleThingsEntityAuditConfig,
 *         sonata_exporter?: SonataExporterConfig,
 *         twig_extra?: TwigExtraConfig,
 *         turbo?: TurboConfig,
 *         league_oauth2_server?: LeagueOauth2ServerConfig,
 *         stimulus?: StimulusConfig,
 *         fpt_stripe?: FptStripeConfig,
 *         ux_icons?: UxIconsConfig,
 *         twig_component?: TwigComponentConfig,
 *         live_component?: LiveComponentConfig,
 *         ux_map?: UxMapConfig,
 *         symfonycasts_verify_email?: SymfonycastsVerifyEmailConfig,
 *         dama_doctrine_test?: DamaDoctrineTestConfig,
 *         zenstruck_foundry?: ZenstruckFoundryConfig,
 *     },
 *     ...<string, ExtensionType|array{ // extra keys must follow the when@%env% pattern or match an extension alias
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         ...<string, ExtensionType>,
 *     }>
 * }
 */
final class App
{
    /**
     * @param ConfigType $config
     *
     * @psalm-return ConfigType
     */
    public static function config(array $config): array
    {
        return AppReference::config($config);
    }
}

namespace Symfony\Component\Routing\Loader\Configurator;

/**
 * This class provides array-shapes for configuring the routes of an application.
 *
 * Example:
 *
 *     ```php
 *     // config/routes.php
 *     namespace Symfony\Component\Routing\Loader\Configurator;
 *
 *     return Routes::config([
 *         'controllers' => [
 *             'resource' => 'routing.controllers',
 *         ],
 *     ]);
 *     ```
 *
 * @psalm-type RouteConfig = array{
 *     path: string|array<string,string>,
 *     controller?: string,
 *     methods?: string|list<string>,
 *     requirements?: array<string,string>,
 *     defaults?: array<string,mixed>,
 *     options?: array<string,mixed>,
 *     host?: string|array<string,string>,
 *     schemes?: string|list<string>,
 *     condition?: string,
 *     locale?: string,
 *     format?: string,
 *     utf8?: bool,
 *     stateless?: bool,
 * }
 * @psalm-type ImportConfig = array{
 *     resource: string,
 *     type?: string,
 *     exclude?: string|list<string>,
 *     prefix?: string|array<string,string>,
 *     name_prefix?: string,
 *     trailing_slash_on_root?: bool,
 *     controller?: string,
 *     methods?: string|list<string>,
 *     requirements?: array<string,string>,
 *     defaults?: array<string,mixed>,
 *     options?: array<string,mixed>,
 *     host?: string|array<string,string>,
 *     schemes?: string|list<string>,
 *     condition?: string,
 *     locale?: string,
 *     format?: string,
 *     utf8?: bool,
 *     stateless?: bool,
 * }
 * @psalm-type AliasConfig = array{
 *     alias: string,
 *     deprecated?: array{package:string, version:string, message?:string},
 * }
 * @psalm-type RoutesConfig = array{
 *     "when@dev"?: array<string, RouteConfig|ImportConfig|AliasConfig>,
 *     "when@panther"?: array<string, RouteConfig|ImportConfig|AliasConfig>,
 *     "when@prod"?: array<string, RouteConfig|ImportConfig|AliasConfig>,
 *     "when@test"?: array<string, RouteConfig|ImportConfig|AliasConfig>,
 *     ...<string, RouteConfig|ImportConfig|AliasConfig>
 * }
 */
final class Routes
{
    /**
     * @param RoutesConfig $config
     *
     * @psalm-return RoutesConfig
     */
    public static function config(array $config): array
    {
        return $config;
    }
}

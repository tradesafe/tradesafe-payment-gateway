<?php

namespace TradeSafe\Helpers;

use GraphQL\Client;
use GraphQL\Mutation;
use GraphQL\Query;
use GraphQL\RawObject;
use GraphQL\Variable;

class TradeSafeApiClient {



	private string $clientId;
	private string $clientSecret;
	private string $clientRedirectUri;

	private string $apiDomain;
	private string $authDomain;

	private string $user_agent;

	public function __construct() {
		// Domains are in a separate file to help with internal development and testing.
		require __DIR__ . '/../config.php';

		if ( file_exists( __DIR__ . '/../config.local.php' ) ) {
			require __DIR__ . '/../config.local.php';
		}

		$settings = get_option( 'woocommerce_tradesafe_settings', array() );

		$this->clientId          = $settings['client_id'];
		$this->clientSecret      = $settings['client_secret'];
		$this->clientRedirectUri = site_url( '/tradesafe/oauth/callback/' );

		$this->authDomain = $auth_domain;

		$url_parts        = parse_url( get_site_url() );
		$this->user_agent = 'WC/' . WC_GATEWAY_TRADESAFE_VERSION . '/' . $url_parts['host'];

		if ( tradesafe_is_prod() ) {
			$this->apiDomain = $api_domains['prod'];
		} else {
			$this->apiDomain = $api_domains['sit'];
		}
	}

	public function client() {
		$token = $this->generateToken();

		$authorizationHeaders = array(
			'Authorization' => 'Bearer ' . $token,
		);

		$httpOptions = array(
			'connect_timeout' => 5,
			'timeout'         => 15,
			'headers'         => array(
				'Accept-Encoding' => 'gzip',
				'User-Agent'      => $this->user_agent,
			),
		);

		if ( true === WP_DEBUG ) {
			$httpOptions['verify'] = false;
		}

		return new Client(
			sprintf( '%s/graphql', $this->apiDomain ),
			$authorizationHeaders,
			$httpOptions,
		);
	}

	public function ping() {
		$api_status        = false;
		$api_status_reason = null;

		$auth_status        = false;
		$auth_status_reason = null;

		try {
			$client = new \GuzzleHttp\Client(
				array(
					'base_uri' => sprintf( '%s/', $this->apiDomain ),
					'timeout'  => 2.0,
					'headers'  => array(
						'Accept-Encoding' => 'gzip',
						'User-Agent'      => $this->user_agent,
					),
				)
			);

			$response = $client->request( 'GET', 'api/ping' );

			if ( $response->getStatusCode() === 200 && $response->getBody()->getContents() === 'pong' ) {
				$api_status = true;
			} else {
				$auth_status_reason = sprintf( '[%s]: %s', $response->getStatusCode(), $response->getBody()->getContents() );
			}
		} catch ( \Exception $e ) {
			$api_status_reason = $e->getMessage();
		} catch ( \Throwable $e ) {
			$api_status_reason = $e->getMessage();
		}

		try {
			$client = new \GuzzleHttp\Client(
				array(
					'base_uri' => sprintf( '%s/', $this->authDomain ),
					'timeout'  => 2.0,
					'headers'  => array(
						'Accept-Encoding' => 'gzip',
						'User-Agent'      => $this->user_agent,
					),
				)
			);

			$response = $client->request( 'GET', 'ping' );

			if ( $response->getStatusCode() === 200 && $response->getBody()->getContents() === 'pong' ) {
				$auth_status = true;
			} else {
				$auth_status_reason = sprintf( '[%s]: %s', $response->getStatusCode(), $response->getBody()->getContents() );
			}
		} catch ( \Exception $e ) {
			$auth_status_reason = $e->getMessage();
		} catch ( \Throwable $e ) {
			$auth_status_reason = 'Authentication Failed';
		}

		return array(
			'api'  => array(
				'domain' => $this->apiDomain,
				'status' => $api_status,
				'reason' => $api_status_reason,
			),
			'auth' => array(
				'domain' => $this->authDomain,
				'status' => $auth_status,
				'reason' => $auth_status_reason,
			),
		);
	}

	public function production() {
		return tradesafe_is_prod();
	}

	public function generateToken() {
		$tradesafe_token = get_option( 'tradesafe_api_access' );

		if ( is_array( $tradesafe_token )
			&& isset( $tradesafe_token['expires'] )
			&& $tradesafe_token['expires'] > ( time() + 120 ) ) {
			return $tradesafe_token['token'];
		}

		try {
			$httpClient = new \GuzzleHttp\Client(
				array(
					'headers' => array(
						'Accept-Encoding' => 'gzip',
						'User-Agent'      => $this->user_agent,
					),
				)
			);

			$provider = new \League\OAuth2\Client\Provider\GenericProvider(
				array(
					'clientId'                => $this->clientId,
					'clientSecret'            => $this->clientSecret,
					'urlAuthorize'            => $this->authDomain . '/oauth/authorize',
					'urlAccessToken'          => $this->authDomain . '/oauth/token',
					'urlResourceOwnerDetails' => $this->authDomain . '/oauth/resource',
				),
				array(
					'httpClient' => $httpClient,
				)
			);

			$access_token = $provider->getAccessToken( 'client_credentials' );

			update_option(
				'tradesafe_api_access',
				array(
					'token'   => $access_token->getToken(),
					'expires' => $access_token->getExpires(),
				)
			);

			return $access_token->getToken();
		} catch ( \Exception $e ) {
			$this->error = $e->getMessage();

			error_log( $e->getMessage() );

			return null;
		} catch ( \Throwable $e ) {
			$this->error = $e->getMessage();

			error_log( $e->getMessage() );

			return null;
		}
	}

	public function getEnum( string $name ) {
		$cache_name = 'tradesafe_enum_' . $name;

		if ( get_transient( $cache_name ) ) {
			return get_transient( $cache_name );
		}

		$gql = new Query( '__type(name: "' . $name . '")' );

		$gql->setSelectionSet(
			array(
				( new Query( 'enumValues' ) )
					->setSelectionSet(
						array(
							'name',
							'description',
						)
					),
			)
		);

		$response = $this->client()->runQuery( $gql, true );
		$data     = $response->getData();

		$options = array();
		foreach ( $data['__type']['enumValues'] as $enum ) {
			$options[ $enum['name'] ] = $enum['description'];
		}

		set_transient( $cache_name, $options, 24 * 60 * 60 );

		return $options;
	}

	public function profile() {
		try {
			$gql = ( new Query( 'apiProfile' ) );

			$gql->setSelectionSet(
				array(
					'token',
				)
			);

			$response = $this->client()->runQuery( $gql, true );
			$result   = $response->getData();

			return $this->getToken( $result['apiProfile']['token'] );
		} catch ( \Exception $e ) {
			return array(
				'error' => $e->getMessage(),
			);
		} catch ( \Throwable $e ) {
			return array(
				'error' => $e->getMessage(),
			);
		}
	}

	public function clientInfo() {
		if ( get_transient( 'tradesafe_client_info' ) ) {
			return get_transient( 'tradesafe_client_info' );
		}

		$gql = ( new Query( 'clientInfo' ) );

		$gql->setSelectionSet(
			array(
				'id',
				'name',
				'callback',
				'organizationId',
				'production',
			)
		);

		try {
			$response = $this->client()->runQuery( $gql, true );
			$result   = $response->getData();

			set_transient( 'tradesafe_client_info', $result['clientInfo'], 600 );

			return $result['clientInfo'];
		} catch ( \Exception $e ) {
			error_log( $e->getMessage() );
			return array();
		} catch ( \Throwable $e ) {
			error_log( $e->getMessage() );
			return array();
		}
	}

	public function getToken( $id, $bankAccount = false ) {
		$gql = ( new Query( 'token' ) );

		$gql->setArguments( array( 'id' => $id ) );

		$query = array(
			'id',
			'name',
			'reference',
			'balance',
			'valid',
			( new Query( 'user' ) )
				->setSelectionSet(
					array(
						'givenName',
						'familyName',
						'email',
						'mobile',
						'idNumber',
						'idType',
						'idCountry',
					)
				),
			( new Query( 'organization' ) )
				->setSelectionSet(
					array(
						'name',
						'tradeName',
						'type',
						'registration',
						'taxNumber',
					)
				),
			( new Query( 'settings' ) )
				->setSelectionSet(
					array(
						( new Query( 'payout' ) )
							->setSelectionSet(
								array(
									'interval',
								)
							),
					)
				),
		);

		if ( $bankAccount ) {
			$query[] = ( new Query( 'bankAccount' ) )
				->setSelectionSet(
					array(
						'accountNumber',
						'accountType',
						'bank',
						'branchCode',
						'bankName',
					)
				);
		}

		$gql->setSelectionSet( $query );

		try {
			$response = $this->client()->runQuery( $gql, true );
			$result   = $response->getData();

			return $result['token'];
		} catch ( \Exception $e ) {
			error_log( $e->getMessage() );
			return null;
		} catch ( \Throwable $e ) {
			error_log( $e->getMessage() );
			return null;
		}
	}

	public function createToken( $user, $organization = null, $bankAccount = null, $payout_interval = 'WEEKLY' ) {
		$gql = ( new Mutation( 'tokenCreate' ) );

		if ( empty( $user['mobile'] ) ) {
			unset( $user['mobile'] );
		}

		$variables = array(
			'input' => array(
				'user'         => $user,
				'organization' => $organization,
				'bankAccount'  => $bankAccount,
				'settings'     => array(
					'payout' => array(
						'interval' => $payout_interval,
					),
				),
			),
		);

		$gql->setVariables( array( new Variable( 'input', 'TokenInput', true ) ) );
		$gql->setArguments( array( 'input' => '$input' ) );

		$gql->setSelectionSet(
			array(
				'id',
				'name',
				'reference',
				'balance',
				'valid',
				( new Query( 'user' ) )
					->setSelectionSet(
						array(
							'givenName',
							'familyName',
							'email',
							'mobile',
							'idNumber',
						)
					),
				( new Query( 'organization' ) )
					->setSelectionSet(
						array(
							'name',
							'tradeName',
							'type',
							'registration',
							'taxNumber',
						)
					),
				( new Query( 'bankAccount' ) )
					->setSelectionSet(
						array(
							'accountNumber',
							'accountType',
							'bank',
							'branchCode',
							'bankName',
						)
					),
			)
		);

		$response = $this->client()->runQuery( $gql, true, $variables );
		$result   = $response->getData();

		return $result['tokenCreate'];
	}

	public function updateToken( $tokenId, $user, $organization = null, $bankAccount = null, $payout_interval = 'WEEKLY' ) {
		$gql = ( new Mutation( 'tokenUpdate' ) );

		$variables = array(
			'id'    => $tokenId,
			'input' => array(
				'user'         => $user,
				'organization' => $organization,
				'bankAccount'  => $bankAccount,
				'settings'     => array(
					'payout' => array(
						'interval' => $payout_interval,
					),
				),
			),
		);

		$gql->setVariables(
			array(
				new Variable( 'id', 'ID', true ),
				new Variable( 'input', 'TokenInput', true ),
			)
		);

		$gql->setArguments(
			array(
				'id'    => '$id',
				'input' => '$input',
			)
		);

		$gql->setSelectionSet(
			array(
				'id',
				'name',
				'reference',
				'balance',
				'valid',
				( new Query( 'user' ) )
					->setSelectionSet(
						array(
							'givenName',
							'familyName',
							'email',
							'mobile',
							'idNumber',
						)
					),
				( new Query( 'organization' ) )
					->setSelectionSet(
						array(
							'name',
							'tradeName',
							'type',
							'registration',
							'taxNumber',
						)
					),
				( new Query( 'bankAccount' ) )
					->setSelectionSet(
						array(
							'accountNumber',
							'accountType',
							'bank',
							'branchCode',
							'bankName',
						)
					),
			)
		);

		$response = $this->client()->runQuery( $gql, true, $variables );
		$result   = $response->getData();

		return $result['tokenUpdate'];
	}

	public function getTransaction( $id ) {
		$gql = ( new Query( 'transaction' ) );

		$gql->setArguments( array( 'id' => $id ) );

		$gql->setSelectionSet(
			array(
				'id',
				'state',
				( new Query( 'allocations' ) )
					->setSelectionSet(
						array(
							'id',
							'state',
						)
					),
				( new Query( 'parties' ) )
					->setSelectionSet(
						array(
							'id',
							'role',
							( new Query( 'details' ) )
								->setSelectionSet(
									array(
										'tokenId',
									)
								),
							( new Query( 'calculation' ) )
								->setSelectionSet(
									array(
										'payout',
									)
								),
							( new Query( 'settings' ) )
								->setSelectionSet(
									array(
										( new Query( 'payout' ) )
											->setSelectionSet(
												array(
													'interval',
												)
											),
									)
								),
						)
					),
			)
		);

		$response = $this->client()->runQuery( $gql, true );
		$result   = $response->getData();

		return $result['transaction'];
	}

	public function createTransaction( $transactionData, $allocationData, $partyData ) {
		$gql = ( new Mutation( 'transactionCreate' ) );

		$allocationInput = '';
		$partyInput      = '';

		foreach ( $allocationData as $allocation ) {
			if ( isset( $allocation['units'] )
				&& isset( $allocation['unitCost'] ) ) {
				$allocationInput .= sprintf(
					'{
                    title: "%s",
                    description: """%s""",
                    units: %s,
                    unitCost: %s,
                    daysToDeliver: %s,
                    daysToInspect: %s
                }',
					$allocation['title'],
					$allocation['description'],
					$allocation['units'] ?? 0,
					$allocation['unitCost'] ?? 0,
					$allocation['daysToDeliver'] ?? 14,
					$allocation['daysToInspect'] ?? 7,
				);
			} else {
				$allocationInput .= sprintf(
					'{
                    title: "%s",
                    description: """%s""",
                    value: %s,
                    daysToDeliver: %s,
                    daysToInspect: %s
                }',
					$allocation['title'],
					$allocation['description'],
					$allocation['value'] ?? 0,
					$allocation['daysToDeliver'] ?? 14,
					$allocation['daysToInspect'] ?? 7,
				);
			}
		}

		foreach ( $partyData as $party ) {
			$partyInput .= sprintf(
				'{
                token: "%s",
                email: "%s",
                role: %s,
                fee: %s,
                feeType: %s,
                feeAllocation: %s
            }',
				$party['token'] ?? null,
				$party['email'] ?? null,
				$party['role'] ?? null,
				$party['fee'] ?? 0,
				$party['feeType'] ?? 'PERCENT',
				$party['feeAllocation'] ?? 'SELLER',
			);
		}

		$input = sprintf(
			'{
            title: "%s",
            description: """%s""",
            industry: %s,
            currency: ZAR,
            feeAllocation: %s,
            workflow: %s,
            reference: "%s",
            privacy: %s,
            allocations: {
                create: [
                    %s
                ]
            },
            parties: {
                create: [
                    %s
                ]
            }
        }',
			$transactionData['title'],
			$transactionData['description'],
			$transactionData['industry'] ?? 'GENERAL_GOODS_SERVICES',
			$transactionData['feeAllocation'] ?? 'SELLER',
			$transactionData['workflow'] ?? 'STANDARD',
			$transactionData['reference'] ?? '',
			$transactionData['privacy'] ?? 'NONE',
			$allocationInput,
			$partyInput,
		);

		$gql->setArguments( array( 'input' => new RawObject( $input ) ) );

		$gql->setSelectionSet(
			array(
				'id',
				'title',
				'description',
				'state',
				'industry',
				'feeAllocation',
				( new Query( 'parties' ) )
					->setSelectionSet(
						array(
							'id',
							'name',
							'role',
							( new Query( 'details' ) )
								->setSelectionSet(
									array(
										( new Query( 'user' ) )
											->setSelectionSet(
												array(
													'givenName',
													'familyName',
													'email',
												)
											),
										( new Query( 'organization' ) )
											->setSelectionSet(
												array(
													'name',
													'tradeName',
													'type',
													'registration',
													'taxNumber',
												)
											),
									)
								),
							( new Query( 'calculation' ) )
								->setSelectionSet(
									array(
										'payout',
										'totalFee',
									)
								),
							'fee',
							'feeType',
							'feeAllocation',
						)
					),
				( new Query( 'allocations' ) )
					->setSelectionSet(
						array(
							'id',
							'title',
							'description',
							'value',
							( new Query( 'amendments' ) )
								->setSelectionSet(
									array(
										'id',
										'value',
									)
								),
						)
					),
				( new Query( 'deposits' ) )
					->setSelectionSet(
						array(
							'id',
							'value',
							'method',
							'processed',
							'paymentLink',
						)
					),
				( new Query( 'calculation' ) )
					->setSelectionSet(
						array(
							'baseValue',
							'totalValue',
							'totalDeposits',
							'processingFeePercentage',
							'processingFeeValue',
							'processingFeeVat',
							'processingFeeTotal',
							( new Query( 'gatewayProcessingFees' ) )
								->setSelectionSet(
									array(
										( new Query( 'manualEft' ) )
											->setSelectionSet(
												array(
													'processingFee',
													'totalValue',
												)
											),
										( new Query( 'ecentric' ) )
											->setSelectionSet(
												array(
													'processingFee',
													'totalValue',
												)
											),
										( new Query( 'ozow' ) )
											->setSelectionSet(
												array(
													'processingFee',
													'totalValue',
												)
											),
										( new Query( 'snapscan' ) )
											->setSelectionSet(
												array(
													'processingFee',
													'totalValue',
												)
											),
									)
								),
							( new Query( 'parties' ) )
								->setSelectionSet(
									array(
										'role',
										'deposit',
										'payout',
										'commission',
										'processingFee',
										'agentFee',
										'beneficiaryFee',
										'totalFee',
									)
								),
							( new Query( 'allocations' ) )
								->setSelectionSet(
									array(
										'value',
										'units',
										'unitCost',
										'refund',
										'payout',
										'fee',
										'processingFee',
									)
								),
						)
					),
				'createdAt',
				'updatedAt',
			)
		);

		$response = $this->client()->runQuery( $gql, true );

		$result = $response->getData();

		return $result['transactionCreate'];
	}

	public function cancelTransaction( $id, $comment ) {
		$gql = ( new Mutation( 'transactionCancel' ) );

		$gql->setVariables(
			array(
				new Variable( 'id', 'ID', true ),
				new Variable( 'comment', 'String', true ),
			)
		);

		$variables = array(
			'id'      => $id,
			'comment' => $comment,
		);

		$gql->setArguments(
			array(
				'id'      => '$id',
				'comment' => '$comment',
			)
		);

		$gql->setSelectionSet(
			array(
				'id',
				'state',
			)
		);

		$response = $this->client()->runQuery( $gql, true, $variables );
		$result   = $response->getData();

		return $result['transactionCancel'];
	}

	public function getTransactionDepositLink( $id ) {
		$gql = ( new Mutation( 'checkoutLink' ) );

		$gql->setVariables(
			array(
				new Variable( 'transactionId', 'ID', true ),
			)
		);

		$variables = array(
			'transactionId' => $id,
		);

		$gql->setArguments( array( 'transactionId' => '$transactionId' ) );

		$response = $this->client()->runQuery( $gql, true, $variables );
		$result   = $response->getData();

		return $result['checkoutLink'];
	}

	public function allocationStartDelivery( $id ) {
		$gql = ( new Mutation( 'allocationStartDelivery' ) );

		$gql->setArguments( array( 'id' => $id ) );

		$gql->setSelectionSet(
			array(
				'id',
				'state',
			)
		);

		$response = $this->client()->runQuery( $gql, true );
		$result   = $response->getData();

		return $result['allocationStartDelivery'];
	}

	public function allocationInTransit( $id ) {
		$gql = ( new Mutation( 'allocationInTransit' ) );

		$gql->setArguments( array( 'id' => $id ) );

		$gql->setSelectionSet(
			array(
				'id',
				'state',
			)
		);

		$response = $this->client()->runQuery( $gql, true );
		$result   = $response->getData();

		return $result['allocationInTransit'];
	}

	public function allocationCompleteDelivery( $id ) {
		$gql = ( new Mutation( 'allocationCompleteDelivery' ) );

		$gql->setArguments( array( 'id' => $id ) );

		$gql->setSelectionSet(
			array(
				'id',
				'state',
			)
		);

		$response = $this->client()->runQuery( $gql, true );
		$result   = $response->getData();

		return $result['allocationCompleteDelivery'];
	}

	public function allocationAcceptDelivery( $id ) {
		$gql = ( new Mutation( 'allocationAcceptDelivery' ) );

		$gql->setArguments( array( 'id' => $id ) );

		$gql->setSelectionSet(
			array(
				'id',
				'state',
			)
		);

		$response = $this->client()->runQuery( $gql, true );
		$result   = $response->getData();

		return $result['allocationAcceptDelivery'];
	}

	public function tokenAccountWithdraw( $id, $value, $rtc = false ) {
		$gql = ( new Mutation( 'tokenAccountWithdraw' ) );

		$gql->setArguments(
			array(
				'id'    => $id,
				'value' => $value,
				'rtc'   => $rtc,
			)
		);

		$response = $this->client()->runQuery( $gql, true );
		$result   = $response->getData();

		return $result['tokenAccountWithdraw'];
	}
}

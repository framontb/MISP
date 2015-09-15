<?php
App::uses('AppModel', 'Model');
App::uses('CakeEmail', 'Network/Email');
App::import('Controller', 'Attributes');
Configure::load('config'); // This is needed to load GnuPG.bodyonlyencrypted
/**
 * Event Model
 *
 * @property User $User
 * @property Attribute $Attribute
 */
class Event extends AppModel {

	public $actsAs = array(
		'SysLogLogable.SysLogLogable' => array(	// TODO Audit, logable
			'userModel' => 'User',
			'userKey' => 'user_id',
			'change' => 'full'),
		'Trim',
		'Containable',
	);

/**
 * Display field
 *
 * @var string
 */
	public $displayField = 'id';

	public $virtualFields = array();
	
	public $mispVersion = '2.3.0';

/**
 * Description field
 *
 * @var array
 */
	public $fieldDescriptions = array(
		'threat_level_id' => array('desc' => 'Risk levels: *low* means mass-malware, *medium* means APT malware, *high* means sophisticated APT malware or 0-day attack', 'formdesc' => 'Risk levels: low: mass-malware medium: APT malware high: sophisticated APT malware or 0-day attack'),
		'classification' => array('desc' => 'Set the Traffic Light Protocol classification. <ol><li><em>TLP:AMBER</em>- Share only within the organization on a need-to-know basis</li><li><em>TLP:GREEN:NeedToKnow</em>- Share within your constituency on the need-to-know basis.</li><li><em>TLP:GREEN</em>- Share within your constituency.</li></ol>'),
		'submittedgfi' => array('desc' => 'GFI sandbox: export upload', 'formdesc' => 'GFI sandbox: export upload'),
		'submittedioc' => array('desc' => '', 'formdesc' => ''),
		'analysis' => array('desc' => 'Analysis Levels: *Initial* means the event has just been created, *Ongoing* means that the event is being populated, *Complete* means that the event\'s creation is complete', 'formdesc' => 'Analysis levels: Initial: event has been started Ongoing: event population is in progress Complete: event creation has finished'),
		'distribution' => array('desc' => 'Describes who will have access to the event.')
	);

	/*public $riskDescriptions = array(
		'Undefined' => array('desc' => '*undefined* no risk', 'formdesc' => 'No risk'),
		'Low' => array('desc' => '*low* means mass-malware', 'formdesc' => 'Mass-malware'),
		'Medium' => array('desc' => '*medium* means APT malware', 'formdesc' => 'APT malware'),
		'High' => array('desc' => '*high* means sophisticated APT malware or 0-day attack', 'formdesc' => 'Sophisticated APT malware or 0-day attack')
	);*/

	public $analysisDescriptions = array(
		0 => array('desc' => '*Initial* means the event has just been created', 'formdesc' => 'Creation started'),
		1 => array('desc' => '*Ongoing* means that the event is being populated', 'formdesc' => 'Creation ongoing'),
		2 => array('desc' => '*Complete* means that the event\'s creation is complete', 'formdesc' => 'Creation complete')
	);

	public $distributionDescriptions = array(
		0 => array('desc' => 'This field determines the current distribution of the event', 'formdesc' => "This setting will only allow members of your organisation on this server to see it."),
		1 => array('desc' => 'This field determines the current distribution of the event', 'formdesc' => "Users that are part of your MISP community will be able to see the event. This includes your own organisation, organisations on this MISP server and organisations running MISP servers that synchronise with this server. Any other organisations connected to such linked servers will be restricted from seeing the event. Use this option if you are on the central hub of this community."), // former Community
		2 => array('desc' => 'This field determines the current distribution of the event', 'formdesc' => "Users that are part of your MISP community will be able to see the event. This includes all organisations on this MISP server, all organisations on MISP servers synchronising with this server and the hosting organisations of servers that connect to those afore mentioned servers (so basically any server that is 2 hops away from this one). Any other organisations connected to linked servers that are 2 hops away from this will be restricted from seeing the event. Use this option if this server isn't the central MISP hub of the community but is connected to it."),
		3 => array('desc' => 'This field determines the current distribution of the event', 'formdesc' => "This will share the event with all MISP communities, allowing the event to be freely propagated from one server to the next."),
	);

	public $analysisLevels = array(
		0 => 'Initial', 1 => 'Ongoing', 2 => 'Completed'
	);

	public $distributionLevels = array(
		0 => 'Your organisation only', 1 => 'This community only', 2 => 'Connected communities', 3 => 'All communities'
	);

	public $export_types = array(
			'xml' => array(
					'extension' => '.xml',
					'type' => 'XML',
					'description' => 'Click this to download all events and attributes that you have access to <small>(except file attachments)</small> in a custom XML format.',
			),
			'csv_sig' => array(
					'extension' => '.csv',
					'type' => 'CSV_Sig',
					'description' => 'Click this to download all attributes that are indicators and that you have access to <small>(except file attachments)</small> in CSV format.',
			),
			'csv_all' => array(
					'extension' => '.csv',
					'type' => 'CSV_All',
					'description' => 'Click this to download all attributes that you have access to <small>(except file attachments)</small> in CSV format.',
			),
			'suricata' => array(
					'extension' => '.rules',
					'type' => 'Suricata',
					'description' => 'Click this to download all network related attributes that you have access to under the Suricata rule format. Only published events and attributes marked as IDS Signature are exported. Administration is able to maintain a whitelist containing host, domain name and IP numbers to exclude from the NIDS export.',
			),
			'snort' => array(
					'extension' => '.rules',
					'type' => 'Snort',
					'description' => 'Click this to download all network related attributes that you have access to under the Snort rule format. Only published events and attributes marked as IDS Signature are exported. Administration is able to maintain a whitelist containing host, domain name and IP numbers to exclude from the NIDS export.',
			),
			'rpz' => array(
					'extension' => '.txt',
					'type' => 'RPZ',
					'description' => 'Click this to download an RPZ Zone file generated from all ip-src/ip-dst, hostname, domain attributes. This can be useful for DNS level firewalling. Only published events and attributes marked as IDS Signature are exported.'
			),
			'md5' => array(
					'extension' => '.txt',
					'type' => 'MD5',
					'description' => 'Click on one of these two buttons to download all MD5 checksums contained in file-related attributes. This list can be used to feed forensic software when searching for susipicious files. Only published events and attributes marked as IDS Signature are exported.',
			),
			'sha1' => array(
					'extension' => '.txt',
					'type' => 'SHA1',
					'description' => 'Click on one of these two buttons to download all SHA1 checksums contained in file-related attributes. This list can be used to feed forensic software when searching for susipicious files. Only published events and attributes marked as IDS Signature are exported.',
			),
			'text' => array(
					'extension' => '.txt',
					'type' => 'TEXT',
					'description' => 'Click on one of the buttons below to download all the attributes with the matching type. This list can be used to feed forensic software when searching for susipicious files. Only published events and attributes marked as IDS Signature are exported.'
			),
	);
	
	public $csv_event_context_fields_to_fetch = array(
	 			'info' => 'event_info', 
	 			'org' => 'event_member_org',  
	 			'orgc' => 'event_source_org', 
	 			'distribution' => 'event_distribution', 
	 			'threat_level_id' => 'event_threat_level_id', 
	 			'analysis' => 'event_analysis', 
	 			'date' => 'event_date', 
	 	);
	
/**
 * Validation rules
 *
 * @var array
 */
	public $validate = array(
		'org' => array(
			'notempty' => array(
				'rule' => array('notempty'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'orgc' => array(
			'notempty' => array(
				'rule' => array('notempty'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'date' => array(
			'date' => array(
				'rule' => array('date'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				'required' => true,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'threat_level_id' => array(
			'notempty' => array(
				'rule' => array('inList', array('1', '2', '3', '4')),
				'message' => 'Options : 1, 2, 3, 4 (for High, Medium, Low, Undefined)',
				'required' => true
			),
		),

		'distribution' => array(
			'rule' => array('inList', array('0', '1', '2', '3')),
			'message' => 'Options : Your organisation only, This community only, Connected communities, All communities',
			//'allowEmpty' => false,
			'required' => true,
			//'last' => false, // Stop validation after this rule
			//'on' => 'create', // Limit validation to 'create' or 'update' operations

		),
		'analysis' => array(
			'rule' => array('inList', array('0', '1', '2')),
				'message' => 'Options : 0, 1, 2 (for Initial, Ongoing, Completed)',
				//'allowEmpty' => false,
				'required' => true,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
		),
		'info' => array(
			'notempty' => array(
				'rule' => array('notempty'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'user_id' => array(
			'numeric' => array(
				'rule' => array('numeric'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'user_id' => array(
			'numeric' => array(
				'rule' => array('numeric'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'published' => array(
			'boolean' => array(
				'rule' => array('boolean'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'uuid' => array(
			'uuid' => array(
				'rule' => array('uuid'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		//'classification' => array(
		//		'rule' => array('inList', array('TLP:AMBER', 'TLP:GREEN:NeedToKnow', 'TLP:GREEN')),
		//		//'message' => 'Your custom message here',
		//		//'allowEmpty' => false,
		//		'required' => true,
		//		//'last' => false, // Stop validation after this rule
		//		//'on' => 'create', // Limit validation to 'create' or 'update' operations
		//),
	);

	public function __construct($id = false, $table = null, $ds = null) {
		parent::__construct($id, $table, $ds);
		//$this->virtualFields = Set::merge($this->virtualFields, array(
//			'distribution' => 'IF (Event.private=true AND Event.cluster=false, "Your organization only", IF (Event.private=true AND Event.cluster=true, "This server-only", IF (Event.private=false AND Event.cluster=true, "This Community-only", IF (Event.communitie=true, "Connected communities" , "All communities"))))',
	//	));
	}

	//The Associations below have been created with all possible keys, those that are not needed can be removed

/**
 * belongsTo associations
 *
 * @var array
 */
	public $belongsTo = array(
		//'Org' => array(
		//	'className' => 'Org',
		//	'foreignKey' => 'org',
		//	'conditions' => '',
		//	'fields' => '',
		//	'order' => ''
		//)
		'User' => array(
			'className' => 'User',
			'foreignKey' => 'user_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		),
		'ThreatLevel' => array(
			'className' => 'ThreatLevel',
			'foreignKey' => 'threat_level_id'
		)
	);

/**
 * hasMany associations
 *
 * @var array
 *
 * @throws InternalErrorException // TODO Exception
 */
	public $hasMany = array(
		'Attribute' => array(
			'className' => 'Attribute',
			'foreignKey' => 'event_id',
			'dependent' => true,	// cascade deletes
			'conditions' => '',
			'fields' => '',
			'order' => array('Attribute.category ASC', 'Attribute.type ASC'),
			'limit' => '',
			'offset' => '',
			'exclusive' => '',
			'finderQuery' => '',
			'counterQuery' => ''
		),
		'ShadowAttribute' => array(
				'className' => 'ShadowAttribute',
				'foreignKey' => 'event_id',
				'dependent' => true,	// cascade deletes
				'conditions' => '',
				'fields' => '',
				'order' => array('ShadowAttribute.old_id DESC', 'ShadowAttribute.old_id DESC'),
				'limit' => '',
				'offset' => '',
				'exclusive' => '',
				'finderQuery' => '',
				'counterQuery' => ''
		),
		'EventTag' => array(
				'className' => 'EventTag',
				'dependent' => true,
		)
	);

	public function beforeDelete($cascade = true) {
		// blacklist the event UUID if the feature is enabled
		if (Configure::read('MISP.enableEventBlacklisting')) {
			$event = $this->find('first', array(
					'recursive' => -1,
					'fields' => array('uuid'),
					'conditions' => array('id' => $this->id),
			));
			$this->EventBlacklist = ClassRegistry::init('EventBlacklist');
			$this->EventBlacklist->create();
			$this->EventBlacklist->save(array('event_uuid' => $this->data['Event']['uuid']));
		}
		
		// delete all of the event->tag combinations that involve the deleted event
		$this->EventTag->deleteAll(array('event_id' => $this->id));
		
		// FIXME secure this filesystem access/delete by not allowing to change directories or go outside of the directory container.
		// only delete the file if it exists
		$filepath = APP . "files" . DS . $this->id;
		App::uses('Folder', 'Utility');
		$file = new Folder ($filepath);
		if (is_dir($filepath)) {
			if (!$this->destroyDir($filepath)) {
				throw new InternalErrorException('Delete of event file directory failed. Please report to administrator.');
			}
		}
	}

	public function destroyDir($dir) {
	if (!is_dir($dir) || is_link($dir)) return unlink($dir);
		foreach (scandir($dir) as $file) {
			if ($file == '.' || $file == '..') continue;
			if (!$this->destroyDir($dir . DS . $file)) {
				chmod($dir . DS . $file, 0777);
				if (!$this->destroyDir($dir . DS . $file)) return false;
			};
		}
		return rmdir($dir);
	}

	public function beforeValidate($options = array()) {
		parent::beforeValidate();
		// analysis - setting correct vars
		// TODO refactor analysis into an Enum (in the database)
		if (isset($this->data['Event']['analysis'])) {
			switch($this->data['Event']['analysis']){
			    case 'Initial':
			        $this->data['Event']['analysis'] = 0;
			        break;
			    case 'Ongoing':
			        $this->data['Event']['analysis'] = 1;
			        break;
			    case 'Completed':
			        $this->data['Event']['analysis'] = 2;
			        break;
			}
		}

		// generate UUID if it doesn't exist
		if (empty($this->data['Event']['uuid'])) {
			$this->data['Event']['uuid'] = String::uuid();
		}
		// generate timestamp if it doesn't exist
		if (empty($this->data['Event']['timestamp'])) {
			$date = new DateTime();
			$this->data['Event']['timestamp'] = $date->getTimestamp();
		}
	}

	public function isOwnedByOrg($eventid, $org) {
		return $this->field('id', array('id' => $eventid, 'org' => $org)) === $eventid;
	}

	public function getRelatedEvents($me, $isSiteAdmin = false, $eventId = null) {
		if ($eventId == null) $eventId = $this->data['Event']['id'];
		$this->Correlation = ClassRegistry::init('Correlation');
		// search the correlation table for the event ids of the related events
		if (!$isSiteAdmin) {
		    $conditionsCorrelation = array('AND' =>
		            array('Correlation.1_event_id' => $eventId),
		            array("OR" => array(
		                    'Correlation.org' => $me['org'],
		                    'Correlation.private' => 0),
		            ));
		} else {
		    $conditionsCorrelation = array('Correlation.1_event_id' => $eventId);
		}
		$correlations = $this->Correlation->find('all',array(
		        'fields' => 'Correlation.event_id',
		        'conditions' => $conditionsCorrelation,
		        'recursive' => 0,
		        'order' => array('Correlation.event_id DESC')));

		$relatedEventIds = array();
		foreach ($correlations as $correlation) {
			$relatedEventIds[] = $correlation['Correlation']['event_id'];
		}
		$relatedEventIds = array_unique($relatedEventIds);
		// now look up the event data for these attributes
		$conditions = array("Event.id" => $relatedEventIds);
		$relatedEvents = $this->find('all',
							array('conditions' => $conditions,
								'recursive' => 0,
								'order' => 'Event.date DESC',
								'fields' => 'Event.*'
								)
		);
		return $relatedEvents;
	}

	public function getRelatedAttributes($me, $isSiteAdmin = false, $id = null) {
		if ($id == null) $id = $this->data['Event']['id'];
		$this->Correlation = ClassRegistry::init('Correlation');
		// search the correlation table for the event ids of the related attributes
		if (!$isSiteAdmin) {
		    $conditionsCorrelation = array('AND' =>
		            array('Correlation.1_event_id' => $id),
		            array("OR" => array(
		                    'Correlation.org' => $me['org'],
		                    'Correlation.private' => 0),
		            ));
		} else {
		    $conditionsCorrelation = array('Correlation.1_event_id' => $id);
		}
		$correlations = $this->Correlation->find('all',array(
		        'fields' => 'Correlation.*',
		        'conditions' => $conditionsCorrelation,
		        'recursive' => 0,
		        'order' => array('Correlation.event_id DESC')));
		$relatedAttributes = array();
		foreach($correlations as $correlation) {
			$current = array(
		            'id' => $correlation['Correlation']['event_id'],
		            'org' => $correlation['Correlation']['org'],
		    		'info' => $correlation['Correlation']['info']
		    );
			if (empty($relatedAttributes[$correlation['Correlation']['1_attribute_id']]) || !in_array($current, $relatedAttributes[$correlation['Correlation']['1_attribute_id']])) {
		    	$relatedAttributes[$correlation['Correlation']['1_attribute_id']][] = $current;
			}
		}
		return $relatedAttributes;
	}

/**
 * Clean up an Event Array that was received by an XML request.
 * The structure needs to be changed a little bit to be compatible with what CakePHP expects
 *
 * This function receives the reference of the variable, so no return is required as it directly
 * modifies the original data.
 *
 * @param &$data The reference to the variable
 *
 * @throws InternalErrorException
 */
	public function cleanupEventArrayFromXML(&$data) {
		$objects = array('Attribute', 'ShadowAttribute');
		
		foreach ($objects as $object) {
			// Workaround for different structure in XML/array than what CakePHP expects
			if (isset($data['Event'][$object]) && is_array($data['Event'][$object]) && count($data['Event'][$object])) {
				if (is_numeric(implode(array_keys($data['Event'][$object]), ''))) {
					// normal array of multiple Attributes
					$data[$object] = $data['Event'][$object];
				} else {
					// single attribute
					$data[$object][0] = $data['Event'][$object];
				}
			}
			unset($data['Event'][$object]);
		}
		return $data;
	}

	public function uploadEventToServer($event, $server, $HttpSocket = null) {
		$updated = null;
		$newLocation = $newTextBody = '';
		$result = $this->restfullEventToServer($event, $server, null, $newLocation, $newTextBody, $HttpSocket);
		if ($result === 403) {
			return 'The distribution level of this event blocks it from being pushed.';
		}
		if (strlen($newLocation) || $result) { // HTTP/1.1 200 OK or 302 Found and Location: http://<newLocation>
			if (strlen($newLocation)) { // HTTP/1.1 302 Found and Location: http://<newLocation>
				//$updated = true;
				$result = $this->restfullEventToServer($event, $server, $newLocation, $newLocation, $newTextBody, $HttpSocket);
				if ($result === 405) {
					return 'You do not have permission to edit this event or the event is up to date.';
				}
			}
			try { // TODO Xml::build() does not throw the XmlException
				$xml = Xml::build($newTextBody);
			} catch (XmlException $e) {
				//throw new InternalErrorException();
				return false;
			}
			// get the remote event_id
			foreach ($xml as $xmlEvent) {
				foreach ($xmlEvent as $key => $value) {
					if ($key == 'id') {
						$remoteId = (int)$value;
						break;
					}
				}
			}
			// get the new attribute uuids in an array
			$newerUuids = array();
			foreach ($event['Attribute'] as $attribute) {
				$newerUuids[$attribute['id']] = $attribute['uuid'];
				$attribute['event_id'] = $remoteId;
			}
			// get the already existing attributes and delete the ones that are not there
			foreach ($xml->Event->Attribute as $attribute) {
				foreach ($attribute as $key => $value) {
					if ($key == 'uuid') {
						if (!in_array((string)$value, $newerUuids)) {
							$anAttr = ClassRegistry::init('Attribute');
							$anAttr->deleteAttributeFromServer((string)$value, $server, $HttpSocket);
						}
					}
				}
			}
		}
		return 'Success';
	}

/**
 * Uploads the event and the associated Attributes to another Server
 * TODO move this to a component
 *
 * @return bool true if success, false or error message if failed
 */
	public function restfullEventToServer($event, $server, $urlPath, &$newLocation, &$newTextBody, $HttpSocket = null) {
		if ($event['Event']['distribution'] < 2) { // never upload private events
			return 403; //"Event is private and non exportable";
		}

		$url = $server['Server']['url'];
		$authkey = $server['Server']['authkey'];
		if (null == $HttpSocket) {
			App::uses('SyncTool', 'Tools');
			$syncTool = new SyncTool();
			$HttpSocket = $syncTool->setupHttpSocket($server);
		}
		$request = array(
				'header' => array(
						'Authorization' => $authkey,
						'Accept' => 'application/xml',
						'Content-Type' => 'application/xml',
						//'Connection' => 'keep-alive' // LATER followup cakephp ticket 2854 about this problem http://cakephp.lighthouseapp.com/projects/42648-cakephp/tickets/2854
				)
		);
		$uri = isset($urlPath) ? $urlPath : $url . '/events';
		// LATER try to do this using a separate EventsController and renderAs() function
		$xmlArray = array();
		// rearrange things to be compatible with the Xml::fromArray()
		if (isset($event['Attribute'])) {
			$event['Event']['Attribute'] = $event['Attribute'];
			unset($event['Attribute']);
		}
		if (isset($event['ShadowAttribute'])) unset($event['ShadowAttribute']);
		
		// cleanup the array from things we do not want to expose
		//unset($event['Event']['org']);
		// remove value1 and value2 from the output
		if (isset($event['Event']['Attribute'])) {
			foreach ($event['Event']['Attribute'] as $key => &$attribute) {
				// do not keep attributes that are private, nor cluster
				if ($attribute['distribution'] < 2) {
					unset($event['Event']['Attribute'][$key]);
					continue; // stop processing this
				}
				// Distribution, correct Connected Community to Community in Attribute
				if ($attribute['distribution'] == 2) {
					$attribute['distribution'] = 1;
				}
				// remove value1 and value2 from the output
				unset($attribute['value1']);
				unset($attribute['value2']);
				// also add the encoded attachment
				if ($this->Attribute->typeIsAttachment($attribute['type'])) {
					$encodedFile = $this->Attribute->base64EncodeAttachment($attribute);
					$attribute['data'] = $encodedFile;
				}
				// Passing the attribute ID together with the attribute could cause the deletion of attributes after a publish/push
				// Basically, if the attribute count differed between two instances, and the instance with the lower attribute
				// count pushed, the old attributes with the same ID got overwritten. Unsetting the ID before pushing it
				// solves the issue and a new attribute is always created.
				unset($attribute['id']);
			}
		} else return 403;
		
		// If we ran out of attributes, or we never had any to begin with, we want to prevent the event from being pushed.
		// It should show up the same way as if the event was not exportable
		if (count($event['Event']['Attribute']) == 0) return 403;
		
		// Distribution, correct All to Community in Event
		if ($event['Event']['distribution'] == 2) {
			$event['Event']['distribution'] = 1;
		}
		// display the XML to the user
		$xmlArray['Event'][] = $event['Event'];
		
		App::uses('XMLConverterTool', 'Tools');
		$converter = new XMLConverterTool();
		$data = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . $converter->event2XML($event) . PHP_EOL;
		
		// LATER validate HTTPS SSL certificate
		$this->Dns = ClassRegistry::init('Dns');
		if ($this->Dns->testipaddress(parse_url($uri, PHP_URL_HOST))) {
			// TODO NETWORK for now do not know how to catch the following..
			// TODO NETWORK No route to host
			$response = $HttpSocket->post($uri, $data, $request);
			switch ($response->code) {
				case '200':	// 200 (OK) + entity-action-result
					if ($response->isOk()) {
						$newTextBody = $response->body();
						$newLocation = null;
						return true;
						//return isset($urlPath) ? $response->body() : true;
					} else {
						try {
							// parse the XML response and keep the reason why it failed
							$xmlArray = Xml::toArray(Xml::build($response->body));
						} catch (XmlException $e) {
							return true; // TODO should be false
						}
						if (strpos($xmlArray['response']['name'],"Event already exists")) {	// strpos, so i can piggyback some value if needed.
							return true;
						} else {
							return $xmlArray['response']['name'];
						}
					}
					break;
				case '302': // Found
					$newLocation = $response->headers['Location'];
					$newTextBody = $response->body();
					return true;
					//return isset($urlPath) ? $response->body() : $response->headers['Location'];
					break;
				case '404': // Not Found
					$newLocation = $response->headers['Location'];
					$newTextBody = $response->body();
					return 404;
					break;
				case '405':
					return 405;
					break;
				case '403': // Not authorised
					return 403;
					break;

			}
		}
	}

/**
 * Deletes the event and the associated Attributes from another Server
 * TODO move this to a component
 *
 * @return bool true if success, error message if failed
 */
	public function deleteEventFromServer($uuid, $server, $HttpSocket=null) {
		// TODO private and delete(?)

		$url = $server['Server']['url'];
		$authkey = $server['Server']['authkey'];
		if (null == $HttpSocket) {
			App::uses('SyncTool', 'Tools');
			$syncTool = new SyncTool();
			$HttpSocket = $syncTool->setupHttpSocket($server);
		}
		$request = array(
				'header' => array(
						'Authorization' => $authkey,
						'Accept' => 'application/xml',
						'Content-Type' => 'application/xml',
						//'Connection' => 'keep-alive' // LATER followup cakephp ticket 2854 about this problem http://cakephp.lighthouseapp.com/projects/42648-cakephp/tickets/2854
				)
		);
		$uri = $url . '/events/0?uuid=' . $uuid;

		// LATER validate HTTPS SSL certificate
		$this->Dns = ClassRegistry::init('Dns');
		if ($this->Dns->testipaddress(parse_url($uri, PHP_URL_HOST))) {
			// TODO NETWORK for now do not know how to catch the following..
			// TODO NETWORK No route to host
			$response = $HttpSocket->delete($uri, array(), $request);
			// TODO REST, DELETE, some responce needed
		}
	}

/**
 * Download a specific event from a Server
 * TODO move this to a component
 * @return array|NULL
 */
	public function downloadEventFromServer($eventId, $server, $HttpSocket=null, $proposalDownload = false) {
		$url = $server['Server']['url'];
		$authkey = $server['Server']['authkey'];
		if (null == $HttpSocket) {
			//$HttpSocket = new HttpSocket(array(
			//		'ssl_verify_peer' => false
			//		));
			App::uses('SyncTool', 'Tools');
			$syncTool = new SyncTool();
			$HttpSocket = $syncTool->setupHttpSocket($server);
		}
		$request = array(
				'header' => array(
						'Authorization' => $authkey,
						'Accept' => 'application/xml',
						'Content-Type' => 'application/xml',
						//'Connection' => 'keep-alive' // LATER followup cakephp ticket 2854 about this problem http://cakephp.lighthouseapp.com/projects/42648-cakephp/tickets/2854
				)
		);
		if (!$proposalDownload) {
			$uri = $url . '/events/' . $eventId;
		} else {
			$uri = $url . '/shadow_attributes/getProposalsByUuid/' . $eventId;
		}
		$response = $HttpSocket->get($uri, $data = '', $request);
		if ($response->isOk()) {
			$xmlArray = Xml::toArray(Xml::build($response->body));
			$xmlArray = $this->updateXMLArray($xmlArray);
			return $xmlArray['response'];
		} else {
			// TODO parse the XML response and keep the reason why it failed
			return null;
		}
	}

/**
 * Get an array of event_ids that are present on the remote server
 * TODO move this to a component
 * @return array of event_ids
 */
	public function getEventIdsFromServer($server, $all = false, $HttpSocket=null) {
		$url = $server['Server']['url'];
		$authkey = $server['Server']['authkey'];

		if (null == $HttpSocket) {
			//$HttpSocket = new HttpSocket(array(
			//		'ssl_verify_peer' => false
			//		));
			App::uses('SyncTool', 'Tools');
			$syncTool = new SyncTool();
			$HttpSocket = $syncTool->setupHttpSocket($server);
		}
		$request = array(
				'header' => array(
						'Authorization' => $authkey,
						'Accept' => 'application/xml',
						'Content-Type' => 'application/xml',
						//'Connection' => 'keep-alive' // LATER followup cakephp ticket 2854 about this problem http://cakephp.lighthouseapp.com/projects/42648-cakephp/tickets/2854
				)
		);
		$uri = $url . '/events/index';
		try {
			$response = $HttpSocket->get($uri, $data = '', $request);
			if ($response->isOk()) {
				//debug($response->body);
				$xml = Xml::build($response->body);
				$eventArray = Xml::toArray($xml);
				// correct $eventArray if just one event
				if (is_array($eventArray['response']['Event']) && isset($eventArray['response']['Event']['id'])) {
					$tmp = $eventArray['response']['Event'];
					unset($eventArray['response']['Event']);
					$eventArray['response']['Event'][0] = $tmp;
				}
				$eventIds = array();
				if ($all) {
					if (isset($eventArray['response']['Event']) && !empty($eventArray['response']['Event'])) foreach ($eventArray['response']['Event'] as $event) {
						$eventIds[] = $event['uuid'];
					}
				} else {
					// multiple events, iterate over the array
					if (isset($eventArray['response']['Event']) && !empty($eventArray['response']['Event'])) {
						foreach ($eventArray['response']['Event'] as &$event) {
							if (1 != $event['published']) {
								continue; // do not keep non-published events
							}
							// get rid of events that are the same timestamp as ours or older, we don't want to transfer the attributes for those
							// The event's timestamp also matches the newest attribute timestamp by default
							if ($this->checkIfNewer($event)) {
								$eventIds[] = $event['id'];
							}
						}
					}
				}
				return $eventIds;
			}
			if ($response->code == '403') {
				return 403;
			}
		} catch (SocketException $e){
			// FIXME refactor this with clean try catch over all http functions
			return $e->getMessage();
		}
		// error, so return null
		return null;
	}

	public function fetchEventIds($org, $isSiteAdmin, $from = false, $to = false, $last = false) {
		$conditions = array();
		if (!$isSiteAdmin) {
			$conditions['OR'] = array(
					"AND" => array(
						'Event.distribution >' => 0,
						Configure::read('MISP.unpublishedprivate') ? array('Event.published =' => 1) : array()
					),
					'Event.org LIKE' => $org
			);
		}
		$fields = array('Event.id', 'Event.org', 'Event.distribution');
		
		if ($from) $conditions['AND'][] = array('Event.date >=' => $from);
		if ($to) $conditions['AND'][] = array('Event.date <=' => $to);
		if ($last) $conditions['AND'][] = array('Event.publish_timestamp >=' => $last);
		
		$params = array(
			'conditions' => $conditions,
			'recursive' => -1,
			'fields' => $fields,
		);
		$results = $this->find('all', $params);
		return $results;
	}
	
	//Once the data about the user is gathered from the appropriate sources, fetchEvent is called from the controller.
	public function fetchEvent($eventid = false, $idList = false, $org, $isSiteAdmin = false, $bkgrProcess = false, $tags = false, $from = false, $to = false, $last = false) {
		if ($eventid) {
			$this->id = $eventid;
			if (!$this->exists()) {
				throw new NotFoundException(__('Invalid event'));
			}
			$conditions = array("Event.id" => $eventid);
		} else {
			$conditions = array();
		}
		$me['org'] = $org;
		// if we come from automation, we may not be logged in - instead we used an auth key in the URL.
		
		$conditionsAttributes = array();
		//restricting to non-private or same org if the user is not a site-admin.
		if (!$isSiteAdmin) {
			$conditions['AND']['OR'] = array(
				"AND" => array(
					'Event.distribution >' => 0,
					Configure::read('MISP.unpublishedprivate') ? array('Event.published =' => 1) : array(),
				),
				'Event.org LIKE' => $org
			);
			$conditionsAttributes['OR'] = array(
				'Attribute.distribution >' => 0,
				'(SELECT events.org FROM events WHERE events.id = Attribute.event_id) LIKE' => $org
			);
		}
		
		if ($from) $conditions['AND'][] = array('Event.date >=' => $from);
		if ($to) $conditions['AND'][] = array('Event.date <=' => $to);
		if ($last) $conditions['AND'][] = array('Event.publish_timestamp >=' => $last);
		
		if ($idList && !$tags) {
			$conditions['AND'][] = array('Event.id' => $idList);
		}
		// If we sent any tags along, load the associated tag names for each attribute
		if ($tags) {
			$tag = ClassRegistry::init('Tag');
			$args = $this->Attribute->dissectArgs($tags);
			$tagArray = $tag->fetchEventTagIds($args[0], $args[1]);
			$temp = array();
			if ($idList) $tagArray[0] = array_intersect($tagArray[0], $idList);
			foreach ($tagArray[0] as $accepted) {
				$temp['OR'][] = array('Event.id' => $accepted);
			}
			$conditions['AND'][] = $temp;
			$temp = array();
			foreach ($tagArray[1] as $rejected) {
				$temp['AND'][] = array('Event.id !=' => $rejected);
			}
			$conditions['AND'][] = $temp;
		}
		
		// removing this for now, we export the to_ids == 0 attributes too, since there is a to_ids field indicating it in the .xml
		// $conditionsAttributes['AND'] = array('Attribute.to_ids =' => 1);
		// Same idea for the published. Just adjust the tools to check for this
		// TODO: It is important to make sure that this is documented
		// $conditions['AND'][] = array('Event.published =' => 1);
		
		// do not expose all the data ...
		$fields = array('Event.id', 'Event.org', 'Event.date', 'Event.threat_level_id', 'Event.info', 'Event.published', 'Event.uuid', 'Event.attribute_count', 'Event.analysis', 'Event.timestamp', 'Event.distribution', 'Event.proposal_email_lock', 'Event.orgc', 'Event.user_id', 'Event.locked', 'Event.publish_timestamp');
		$fieldsAtt = array('Attribute.id', 'Attribute.type', 'Attribute.category', 'Attribute.value', 'Attribute.to_ids', 'Attribute.uuid', 'Attribute.event_id', 'Attribute.distribution', 'Attribute.timestamp', 'Attribute.comment');
		$fieldsShadowAtt = array('ShadowAttribute.id', 'ShadowAttribute.type', 'ShadowAttribute.category', 'ShadowAttribute.value', 'ShadowAttribute.to_ids', 'ShadowAttribute.uuid', 'ShadowAttribute.event_id', 'ShadowAttribute.old_id', 'ShadowAttribute.comment', 'ShadowAttribute.org');
			
		$params = array('conditions' => $conditions,
			'recursive' => 0,
			'fields' => $fields,
			'contain' => array(
				'ThreatLevel' => array(
						'fields' => array('ThreatLevel.name')
				),
				'Attribute' => array(
					'fields' => $fieldsAtt,
					'conditions' => $conditionsAttributes,
				),
				'ShadowAttribute' => array(
					'fields' => $fieldsShadowAtt,
					'conditions' => array('deleted' => 0),
				),
			)
		);
		if ($isSiteAdmin) $params['contain']['User'] = array('fields' => 'email');
		$results = $this->find('all', $params);
		// Do some refactoring with the event
		foreach ($results as $eventKey => &$event) {
			// Let's find all the related events and attach it to the event itself
			$results[$eventKey]['RelatedEvent'] = $this->getRelatedEvents($me, $isSiteAdmin, $event['Event']['id']);
			// Let's also find all the relations for the attributes - this won't be in the xml export though
			$results[$eventKey]['RelatedAttribute'] = $this->getRelatedAttributes($me, $isSiteAdmin, $event['Event']['id']);
			foreach ($event['Attribute'] as $key => &$attribute) {
				$attribute['ShadowAttribute'] = array();
				// If a shadowattribute can be linked to an attribute, link it to it then remove it from the event
				// This is to differentiate between proposals that were made to an attribute for modification and between proposals for new attributes
				foreach ($event['ShadowAttribute'] as $k => &$sa) {
					if(!empty($sa['old_id'])) {
						if ($sa['old_id'] == $attribute['id']) {
							$results[$eventKey]['Attribute'][$key]['ShadowAttribute'][] = $sa;
							unset($results[$eventKey]['ShadowAttribute'][$k]);
						}
					}
				}
			}
		}
		return $results;
	}
	public function csv($org, $isSiteAdmin, $eventid=false, $ignore=false, $attributeIDList = array(), $tags = false, $category = false, $type = false, $includeContext = false, $from = false, $to = false, $last = false) {
		$final = array();
		$attributeList = array();
		$conditions = array();
	 	$econditions = array();
	 	$this->recursive = -1;

	 	// If we are not in the search result csv download function then we need to check what can be downloaded. CSV downloads are already filtered by the search function.
	 	if ($eventid !== 'search') {
	 		if ($from) $econditions['AND'][] = array('Event.date >=' => $from);
	 		if ($to) $econditions['AND'][] = array('Event.date <=' => $to);
	 		if ($last) $econditions['AND'][] = array('Event.publish_timestamp >=' => $last);
	 		// This is for both single event downloads and for full downloads. Org has to be the same as the user's or distribution not org only - if the user is no siteadmin
			if(!$isSiteAdmin) $econditions['AND']['OR'] = array(
				"AND" => array(
					'Event.distribution >' => 0,
					Configure::read('MISP.unpublishedprivate') ? array('Event.published =' => 1) : array(),
				),
				'Event.org =' => $org
			);
	 		if ($eventid == 0 && $ignore == 0) $econditions['AND'][] = array('Event.published =' => 1);
	 		
	 		// If it's a full download (eventid == false) and the user is not a site admin, we need to first find all the events that the user can see and save the IDs
	 		if (!$eventid) {
	 			$this->recursive = -1;
	 			// If we sent any tags along, load the associated tag names for each attribute
	 			if ($tags) {
	 				$tag = ClassRegistry::init('Tag');
	 				$args = $this->Attribute->dissectArgs($tags);
	 				$tagArray = $tag->fetchEventTagIds($args[0], $args[1]);
	 				$temp = array();
	 				foreach ($tagArray[0] as $accepted) {
	 					$temp['OR'][] = array('Event.id' => $accepted);
	 				}
	 				$econditions['AND'][] = $temp;
	 				$temp = array();
	 				foreach ($tagArray[1] as $rejected) {
	 					$temp['AND'][] = array('Event.id !=' => $rejected);
	 				}
	 				$econditions['AND'][] = $temp;
	 			}
	 			// let's add the conditions if we're dealing with a non-siteadmin user
	 			$params = array(
	 					'conditions' => $econditions,
	 					'fields' => array('id', 'distribution', 'org', 'published', 'date'),
	 			);
	 			$events = $this->find('all', $params);
	 			if (empty($events)) return array();
	 		}
	 		// if we have items in events, add their IDs to the conditions. If we're a site admin, or we have a single event selected for download, this should be empty
	 		if (isset($events)) {
	 			foreach ($events as $event) $conditions['AND']['OR'][] = array('Attribute.event_id' => $event['Event']['id']);
	 		}
	 		// if we're downloading a single event, set it as a condition
	 		if ($eventid) $conditions['AND'][] = array('Attribute.event_id' => $eventid);
	 		
	 		//restricting to non-private or same org if the user is not a site-admin.
	 		if (!$ignore) $conditions['AND'][] = array('Attribute.to_ids' => 1);
	 		if ($type) $conditions['AND'][] = array('Attribute.type' => $type);
	 		if ($category) $conditions['AND'][] = array('Attribute.category' => $category);
	 		
	 		if (!$isSiteAdmin) {
	 			$temp = array();
	 			$distribution = array();
	 			array_push($temp, array('Attribute.distribution >' => 0));
	 			array_push($temp, array('(SELECT events.org FROM events WHERE events.id = Attribute.event_id) LIKE' => $org));
	 			$conditions['OR'] = $temp;
	 		}
	 	}
	 	
	 	if ($eventid === 'search') {
		 	foreach ($attributeIDList as $aID) $conditions['AND']['OR'][] = array('Attribute.id' => $aID);
	 	}
	 	$params = array(
	 			'conditions' => $conditions, //array of conditions
	 			'fields' => array('Attribute.event_id', 'Attribute.distribution', 'Attribute.category', 'Attribute.type', 'Attribute.value', 'Attribute.uuid', 'Attribute.to_ids', 'Attribute.timestamp'),
	 	);
	 	$attributes = $this->Attribute->find('all', $params);
	 	foreach ($attributes as &$attribute) {
	 		$attribute['Attribute']['value'] = str_replace(array("\r\n", "\n", "\r"), "", $attribute['Attribute']['value']);
	 		$attribute['Attribute']['value'] = str_replace(array('"'), '""', $attribute['Attribute']['value']);
	 		$attribute['Attribute']['value'] = '"' . $attribute['Attribute']['value'] . '"';
	 		$attribute['Attribute']['timestamp'] = date('Ymd', $attribute['Attribute']['timestamp']);
	 	}
	 	if ($includeContext) $attributes = $this->attachEventInfoToAttributes($attributes, $isSiteAdmin);
	 	return $attributes;
	 }
	 
	 private function attachEventInfoToAttributes($attributes, $isSiteAdmin) {
	 	$TLs = $this->ThreatLevel->find('all', array(
	 		'recursive' => -1,
	 	));
	 	$event_ids = array();
	 	foreach ($attributes as &$attribute) {
	 		if (!in_array($attribute['Attribute']['event_id'], $event_ids)) $event_ids[] = $attribute['Attribute']['event_id'];
	 	}
	 	$context_fields = array('id' => null);
	 	$context_fields = array_merge($context_fields, $this->csv_event_context_fields_to_fetch);
	 	if (!Configure::read('MISP.showorg') && !$isSiteAdmin) {
			unset($context_fields['orgc']);
			unset($context_fields['org']);
	 	} else if (!Configure::read('MISP.showorgalternate') && !$isSiteAdmin) {
	 		$context_fields['orgc'] = 'event_org';
	 		$context_fields['org'] = 'event_owner_org';
	 		unset($context_fields['orgc']);
	 	}
	 	
	 	$events = $this->find('all', array(
	 		'recursive' => -1,
	 		'fields' => array_keys($context_fields),
	 		'conditions' => array('id' => $event_ids),
	 	));
	 	$event_id_data = array();
	 	unset($context_fields['id']);
	 	foreach ($events as $event) {
	 		foreach ($context_fields as $field => $header_name) $event_id_data[$event['Event']['id']][$header_name] = $event['Event'][$field];
	 	}
	 	foreach ($attributes as &$attribute) {
	 		foreach ($context_fields as $field => $header_name) {
	 			if ($header_name == 'event_threat_level_id') {
	 				$attribute['Attribute'][$header_name] = $TLs[$event_id_data[$attribute['Attribute']['event_id']][$header_name]]['ThreatLevel']['name'];
	 			} else if ($header_name == 'event_distribution') {
	 				$attribute['Attribute'][$header_name] = $this->distributionLevels[$event_id_data[$attribute['Attribute']['event_id']][$header_name]];
	 			} else if ($header_name == 'event_analysis') {
	 				$attribute['Attribute'][$header_name] = $this->analysisLevels[$event_id_data[$attribute['Attribute']['event_id']][$header_name]];
	 			} else {
	 				$attribute['Attribute'][$header_name] = $event_id_data[$attribute['Attribute']['event_id']][$header_name];
	 			}
	 		}
	 	}
	 	return $attributes;
	 }
	 
	 public function sendAlertEmailRouter($id, $user) {
	 	if (Configure::read('MISP.background_jobs')) {
	 		$job = ClassRegistry::init('Job');
	 		$job->create();
	 		$data = array(
	 				'worker' => 'email',
	 				'job_type' => 'publish_alert_email',
	 				'job_input' => 'Event: ' . $id,
	 				'status' => 0,
	 				'retries' => 0,
	 				'org' => $user['org'],
	 				'message' => 'Sending...',
	 		);
	 		$job->save($data);
	 		$jobId = $job->id;
	 		$process_id = CakeResque::enqueue(
	 				'email',
	 				'EventShell',
	 				array('alertemail', $user['org'], $jobId, $id)
	 		);
	 		$job->saveField('process_id', $process_id);
	 		return true;
	 	} else {
	 		return ($this->sendAlertEmail($id, $user['org']));
	 	}
	 } 
	
	public function sendAlertEmail($id, $org, $processId = null) {
		$this->recursive = 1;
		$event = $this->read(null, $id);
		
		// Initialise the Job class if we have a background process ID
		// This will keep updating the process's progress bar
		if ($processId) {
			$this->Job = ClassRegistry::init('Job');
		}
		
		// The mail body, h() is NOT needed as we are sending plain-text mails.
		$body = "";
		$body .= '==============================================' . "\n";
		$appendlen = 20;
		$body .= 'URL         : ' . Configure::read('MISP.baseurl') . '/events/view/' . $event['Event']['id'] . "\n";
		$body .= 'Event ID    : ' . $event['Event']['id'] . "\n";
		$body .= 'Date        : ' . $event['Event']['date'] . "\n";
		if (Configure::read('MISP.showorg')) {
			$body .= 'Reported by : ' . $event['Event']['org'] . "\n";
		}
		$body .= 'Distribution: ' . $this->distributionLevels[$event['Event']['distribution']] . "\n";
		$body .= 'Threat Level: ' . $event['ThreatLevel']['name'] . "\n";
		$body .= 'Analysis    : ' . $this->analysisLevels[$event['Event']['analysis']] . "\n";
		$body .= 'Description : ' . $event['Event']['info'] . "\n\n";
		$user['org'] = $org;
		$relatedEvents = $this->getRelatedEvents($user, false);
		if (!empty($relatedEvents)) {
			$body .= '==============================================' . "\n";
			$body .= 'Related to : '. "\n";
			foreach ($relatedEvents as &$relatedEvent) {
				$body .= Configure::read('MISP.baseurl') . '/events/view/' . $relatedEvent['Event']['id'] . ' (' . $relatedEvent['Event']['date'] . ') ' ."\n";
			}
			$body .= '==============================================' . "\n";
		}
		$body .= 'Attributes (* indicates a new or modified attribute)  :' . "\n";
		$bodyTempOther = "";
		if (isset($event['Attribute'])) {
			foreach ($event['Attribute'] as &$attribute) {
				$ids = '';
				if ($attribute['to_ids']) $ids = ' (IDS)';
				if (isset($event['Event']['publish_timestamp']) && isset($attribute['timestamp']) && $attribute['timestamp'] > $event['Event']['publish_timestamp']) {
					$line = '*' . $attribute['type'] . str_repeat(' ', $appendlen - 2 - strlen($attribute['type'])) . ': ' . $attribute['value'] . $ids . "\n";					
				} else {
					$line = $attribute['type'] . str_repeat(' ', $appendlen - 2 - strlen($attribute['type'])) . ': ' . $attribute['value'] . $ids .  "\n";
				}
				//Defanging URLs (Not "links") emails domains/ips in notification emails
				if ('url' == $attribute['type']) {
					$line = str_ireplace("http","hxxp", $line);
				}
				elseif ('email-src' == $attribute['type'] or 'email-dst' == $attribute['type']) {
					$line = str_replace("@","[at]", $line);
				}
				elseif ('domain' == $attribute['type'] or 'ip-src' == $attribute['type'] or 'ip-dst' == $attribute['type']) {
					$line = str_replace(".","[.]", $line);
				}
				
				if ('other' == $attribute['type']) // append the 'other' attribute types to the bottom.
					$bodyTempOther .= $line;
				else $body .= $line;
			}
		}
		if (!empty($bodyTempOther)) {
			$body .= "\n";
		}
		
		if (Configure::read('MISP.extended_alert_subject')) {
			$subject = preg_replace( "/\r|\n/", "", $event['Event']['info']);
			if (strlen($subject) > 58) {
				$subject = substr($subject, 0, 55) . '... - ';
			} else {
				$subject .= " - ";
			}
		} else {
			$subject = '';
		}
		$subject = "[" . Configure::read('MISP.org') . " MISP] Event " . $id . " - " . $subject . $event['ThreatLevel']['name'] . " - TLP Amber";
		
		$body .= $bodyTempOther;	// append the 'other' attribute types to the bottom.
		$body .= '==============================================' . "\n";
		// find out whether the event is private, to limit the alerted user's list to the org only
		if ($event['Event']['distribution'] == 0) {
			$eventIsPrivate = true;
		} else {
			$eventIsPrivate = false;
		}

		$bodyNoEnc = "A new or modified event was just published on " . Configure::read('MISP.baseurl') . "/events/view/" . $event['Event']['id'];
		
		$conditions = array('User.autoalert' => 1);
		if ($eventIsPrivate) $conditions['User.org'] = $event['Event']['org'];

		$alertUsers = $this->User->find('all', array(
				'conditions' => $conditions,
				'recursive' => -1,
		));
		$max = count($alertUsers);

		foreach ($alertUsers as $k => &$user) {
			$this->User->sendEmail($user, $body, $bodyNoEnc, $subject);
			if ($processId) {
				$this->Job->id = $processId;
				$this->Job->saveField('progress', $k / $max * 100);
			}
		}
	 	if ($processId) $this->Job->saveField('message', 'Mails sent.');
	 	// LATER check if sending email succeeded and return appropriate result
	 	return true;
	}
	
	public function sendContactEmail($id, $message, $all, $user, $isSiteAdmin) {
		// fetch the event
		$event = $this->read(null, $id);
		$this->User = ClassRegistry::init('User');
		if (!$all) {
			//Insert extra field here: alertOrg or something, then foreach all the org members
			//limit this array to users with contactalerts turned on!
			$orgMembers = array();
			$this->User->recursive = -1;
			$temp = $this->User->findAllByOrg($event['Event']['orgc'], array('email', 'gpgkey', 'contactalert', 'id'));
			if (empty($temp)) $temp = $this->User->findAllByOrg($event['Event']['org'], array('email', 'gpgkey', 'contactalert', 'id'));
			foreach ($temp as $tempElement) {
				if ($tempElement['User']['contactalert'] || $tempElement['User']['id'] == $event['Event']['user_id']) {
					array_push($orgMembers, $tempElement);
				}
			}
		} else {
			$orgMembers = $this->User->findAllById($event['Event']['user_id'], array('email', 'gpgkey'));
		}
	
		// The mail body, h() is NOT needed as we are sending plain-text mails.
		$body = "";
		$body .= "Hello, \n";
		$body .= "\n";
		$body .= "Someone wants to get in touch with you concerning a MISP event. \n";
		$body .= "\n";
		$body .= "You can reach him at " . $user['User']['email'] . "\n";
		if (!$user['User']['gpgkey'])
			$body .= "His GPG/PGP key is added as attachment to this email. \n";
		$body .= "\n";
		$body .= "He wrote the following message: \n";
		$body .= $message . "\n";
		$body .= "\n";
		$body .= "\n";
		$body .= "The event is the following: \n";
	
		// print the event in mail-format
		// LATER place event-to-email-layout in a function
		$appendlen = 20;
		$body .= 'URL		 : ' . Configure::read('MISP.baseurl') . '/events/view/' . $event['Event']['id'] . "\n";
		$bodyevent = $body;
		$bodyevent .= 'Event	   : ' . $event['Event']['id'] . "\n";
		$bodyevent .= 'Date		: ' . $event['Event']['date'] . "\n";
		if (Configure::read('MISP.showorg')) {
			$bodyevent .= 'Reported by : ' . $event['Event']['org'] . "\n";
		}
		$bodyevent .= 'Risk		: ' . $event['ThreatLevel']['name'] . "\n";
		$bodyevent .= 'Analysis  : ' . $event['Event']['analysis'] . "\n";
		$relatedEvents = $this->getRelatedEvents($user['User'], $isSiteAdmin);
		if (!empty($relatedEvents)) {
			foreach ($relatedEvents as &$relatedEvent) {
				$bodyevent .= 'Related to  : ' . Configure::read('MISP.baseurl') . '/events/view/' . $relatedEvent['Event']['id'] . ' (' . $relatedEvent['Event']['date'] . ')' . "\n";
	
			}
		}
		$bodyevent .= 'Info  : ' . "\n";
		$bodyevent .= $event['Event']['info'] . "\n";
		$bodyevent .= "\n";
		$bodyevent .= 'Attributes  :' . "\n";
		$bodyTempOther = "";
		if (!empty($event['Attribute'])) {
			foreach ($event['Attribute'] as &$attribute) {
				$line = '- ' . $attribute['type'] . str_repeat(' ', $appendlen - 2 - strlen( $attribute['type'])) . ': ' . $attribute['value'] . "\n";
				if ('other' == $attribute['type']) // append the 'other' attribute types to the bottom.
					$bodyTempOther .= $line;
				else $bodyevent .= $line;
			}
		}
		$bodyevent .= "\n";
		$bodyevent .= $bodyTempOther;	// append the 'other' attribute types to the bottom.
		foreach ($orgMembers as &$reporter) {
			$bodyNoEnc = false;
			if (Configure::read('GnuPG.bodyonlyencrypted') && empty($reporter['User']['gpgkey'])) {
				$bodyNoEnc = $body;
			}
			$subject = "[" . Configure::read('MISP.org') . " MISP] Need info about event " . $id . " - TLP Amber";
			$result = $this->User->sendEmail($reporter, $bodyevent, $bodyNoEnc, $subject, $user);
		}
		return $result;
	}
	
	/**
	 * Low level function to add an Event based on an Event $data array
	 *
	 * @return bool true if success
	 */
	public function _add(&$data, $fromXml, $user, $org='', $passAlong = null, $fromPull = false, $jobId = null) {
		if ($jobId) {
			App::import('Component','Auth');
		}
		if (Configure::read('MISP.enableEventBlacklisting') && isset($data['Event']['uuid'])) {
			$event = $this->find('first', array(
					'recursive' => -1,
					'fields' => array('uuid'),
					'conditions' => array('id' => $this->id),
			));
			$this->EventBlacklist = ClassRegistry::init('EventBlacklist');
			$r = $this->EventBlacklist->find('first', array('conditions' => array('event_uuid' => $data['Event']['uuid'])));
			if (!empty($r))	return 'blocked';
		}
		$this->create();
		// force check userid and orgname to be from yourself
		$data['Event']['user_id'] = $user['id'];
		$date = new DateTime();
	
		//if ($this->checkAction('perm_sync')) $data['Event']['org'] = Configure::read('MISP.org');
		//else $data['Event']['org'] = $auth->user('org');
		if ($fromPull) {
			$data['Event']['org'] = $org;
		} else {
			$data['Event']['org'] = $user['org'];
		}
		// set these fields if the event is freshly created and not pushed from another instance.
		// Moved out of if (!$fromXML), since we might get a restful event without the orgc/timestamp set
		if (!isset ($data['Event']['orgc'])) $data['Event']['orgc'] = $data['Event']['org'];
		if ($fromXml) {
			// Workaround for different structure in XML/array than what CakePHP expects
			$data = $this->cleanupEventArrayFromXML($data);
			// the event_id field is not set (normal) so make sure no validation errors are thrown
			// LATER do this with	 $this->validator()->remove('event_id');
			unset($this->Attribute->validate['event_id']);
			unset($this->Attribute->validate['value']['unique']); // otherwise gives bugs because event_id is not set
		}
		unset ($data['Event']['id']);
		if (isset($data['Event']['uuid'])) {
			// check if the uuid already exists
			$existingEventCount = $this->find('count', array('conditions' => array('Event.uuid' => $data['Event']['uuid'])));
			if ($existingEventCount > 0) {
				// RESTfull, set responce location header..so client can find right URL to edit
				if ($fromPull) return false;
				$existingEvent = $this->find('first', array('conditions' => array('Event.uuid' => $data['Event']['uuid'])));
				return $existingEvent['Event']['id'];
			}
		}
		if (isset($data['Attribute'])) {
			foreach ($data['Attribute'] as &$attribute) {
				unset ($attribute['id']);
			}
		}
		// FIXME chri: validatebut  the necessity for all these fields...impact on security !
		$fieldList = array(
				'Event' => array('org', 'orgc', 'date', 'threat_level_id', 'analysis', 'info', 'user_id', 'published', 'uuid', 'timestamp', 'distribution', 'locked'),
				'Attribute' => array('event_id', 'category', 'type', 'value', 'value1', 'value2', 'to_ids', 'uuid', 'revision', 'timestamp', 'distribution', 'comment')
		);
		$saveResult = $this->saveAssociated($data, array('validate' => true, 'fieldList' => $fieldList,
				'atomic' => true));
		// FIXME chri: check if output of $saveResult is what we expect when data not valid, see issue #104
		if ($saveResult) {
			if (!empty($data['Event']['published']) && 1 == $data['Event']['published']) {
				// do the necessary actions to publish the event (email, upload,...)
				if ('true' != Configure::read('MISP.disablerestalert')) {
					$this->sendAlertEmailRouter($this->getId(), $user);
				}
				$this->publish($this->getId(), $passAlong);
			}
			return true;
		} else {
			//throw new MethodNotAllowedException("Validation ERROR: \n".var_export($this->Event->validationErrors, true));
			return false;
		}
	}
	
	public function _edit(&$data, $id, $jobId = null) {
		if ($jobId) {
			App::import('Component','Auth');
		}
		$localEvent = $this->find('first', array('conditions' => array('Event.id' => $id), 'recursive' => -1, 'contain' => array('Attribute', 'ThreatLevel', 'ShadowAttribute')));
		if (!isset ($data['Event']['orgc'])) $data['Event']['orgc'] = $data['Event']['org'];
		if ($localEvent['Event']['timestamp'] < $data['Event']['timestamp']) {
	
		} else {
			return 'Event exists and is the same or newer.';
		}
		if (!$localEvent['Event']['locked']) {
			return 'Event originated on this instance, any changes to it have to be done locally.';
		}
		$fieldList = array(
				'Event' => array('date', 'threat_level_id', 'analysis', 'info', 'published', 'uuid', 'from', 'distribution', 'timestamp'),
				'Attribute' => array('event_id', 'category', 'type', 'value', 'value1', 'value2', 'to_ids', 'uuid', 'distribution', 'timestamp', 'comment')
		);
		$data['Event']['id'] = $localEvent['Event']['id'];
		if (isset($data['Event']['Attribute'])) {
			foreach ($data['Event']['Attribute'] as $k => &$attribute) {
				$existingAttribute = $this->__searchUuidInAttributeArray($attribute['uuid'], $localEvent);
				if (count($existingAttribute)) {
					$data['Event']['Attribute'][$k]['id'] = $existingAttribute['Attribute']['id'];
					// Check if the attribute's timestamp is bigger than the one that already exists.
					// If yes, it means that it's newer, so insert it. If no, it means that it's the same attribute or older - don't insert it, insert the old attribute.
					// Alternatively, we could unset this attribute from the request, but that could lead with issues if we decide that we want to start deleting attributes that don't exist in a pushed event.
					if ($data['Event']['Attribute'][$k]['timestamp'] > $existingAttribute['Attribute']['timestamp']) {
						$data['Event']['Attribute'][$k]['id'] = $existingAttribute['Attribute']['id'];
						$data['Attribute'][] = $data['Event']['Attribute'][$k];
						unset($data['Event']['Attribute'][$k]);
					} else {
					unset($data['Event']['Attribute'][$k]);
						}
				} else {
					unset($data['Event']['Attribute'][$k]['id']);
					$data['Attribute'][] = $data['Event']['Attribute'][$k];
					unset($data['Event']['Attribute'][$k]);
				}
			}
		}
	$data = $this->cleanupEventArrayFromXML($data);
	$saveResult = $this->saveAssociated($data, array('validate' => true, 'fieldList' => $fieldList));
	if ($saveResult) {
		return 'success';
	}
		else return 'Saving the event has failed.';
	}
	
	private function __searchUuidInAttributeArray($uuid, &$attr_array) {
		foreach ($attr_array['Attribute'] as &$attr) {
			if ($attr['uuid'] == $uuid)	return array('Attribute' => $attr);
		}
		return false;
	}
	
	/**
	 * Uploads this specific event to all remote servers
	 * TODO move this to a component
	 *
	 * @return bool true if success, false if, partly, failed
	 */
	public function uploadEventToServersRouter($id, $passAlong = null) {
		// make sure we have all the data of the Event
		$this->id = $id;
		$this->recursive = 1;
		$this->read();
		$this->data['Event']['locked'] = 1;
		// get a list of the servers
		$serverModel = ClassRegistry::init('Server');
		$servers = $serverModel->find('all', array(
				'conditions' => array('Server.push' => true)
		));
		// iterate over the servers and upload the event
		if(empty($servers))
			return true;
	
		$uploaded = true;
		$failedServers = array();
		App::uses('SyncTool', 'Tools');
		foreach ($servers as &$server) {
			$syncTool = new SyncTool();
			$HttpSocket = $syncTool->setupHttpSocket($server);
			//Skip servers where the event has come from.
			if (($passAlong != $server)) {
				$thisUploaded = $this->uploadEventToServer($this->data, $server, $HttpSocket);
				if (!$thisUploaded) {
					$uploaded = !$uploaded ? $uploaded : $thisUploaded;
					$failedServers[] = $server['Server']['url'];
				}
				if (isset($this->data['ShadowAttribute'])) {
					$serverModel->syncProposals($HttpSocket, $server, null, $id, $this);
				}
			}
		}
		if (!$uploaded) {
			return $failedServers;
		} else {
			return true;
		}
	}
	
	public function publishRouter($id, $passAlong = null, $org = null, $email = null) {
		if (Configure::read('MISP.background_jobs')) {
			$job = ClassRegistry::init('Job');
			$job->create();
			$data = array(
					'worker' => 'default',
					'job_type' => 'publish_event',
					'job_input' => 'Event ID: ' . $id,
					'status' => 0,
					'retries' => 0,
					'org' => $org,
					'message' => 'Publishing.',
			);
			$job->save($data);
			$jobId = $job->id;
			$process_id = CakeResque::enqueue(
					'default',
					'EventShell',
					array('publish', $id, $passAlong, $jobId, $org, $email)
			);
			$job->saveField('process_id', $process_id);
			return $process_id;
		} else {
			$result = $this->publish($id, $passAlong);
			return $result;
		}
	}
	
	/**
	 * Performs all the actions required to publish an event
	 *
	 * @param unknown_type $id
	 */
	public function publish($id, $passAlong = null, $jobId = null) {
		$this->id = $id;
		$this->recursive = 0;
		$event = $this->read(null, $id);
		if ($jobId) {
			$this->Behaviors->unload('SysLogLogable.SysLogLogable');
		} else {
			// update the DB to set the published flag
			// for background jobs, this should be done already
			$fieldList = array('published', 'id', 'info', 'publish_timestamp');
			$event['Event']['published'] = 1;
			$event['Event']['publish_timestamp'] = time();
			$this->save($event, array('fieldList' => $fieldList));
		}		
		$uploaded = false;
		if (Configure::read('Plugin.ZeroMQ_enable')) {
			App::uses('PubSubTool', 'Tools');
			$pubSubTool = new PubSubTool();
			$hostOrg = Configure::read('MISP.org');
			$fullEvent = $this->fetchEvent($id, false, $hostOrg, false);
			$pubSubTool->publishEvent($fullEvent[0]);
		}
		if ($event['Event']['distribution'] > 1) {
			$uploaded = $this->uploadEventToServersRouter($id, $passAlong);
		} else {
			return true;
		}
		return $uploaded;
	}
	

	/**
	 *
	 * Sends out an email to all people within the same org
	 * with the request to be contacted about a specific event.
	 * @todo move __sendContactEmail($id, $message) to a better place. (components?)
	 *
	 * @param unknown_type $id The id of the event for wich you want to contact the org.
	 * @param unknown_type $message The custom message that will be appended to the email.
	 * @param unknown_type $all, true: send to org, false: send to person.
	 *
	 * @codingStandardsIgnoreStart
	 * @throws \UnauthorizedException as well. // TODO Exception NotFoundException
	 * @codingStandardsIgnoreEnd
	 *
	 * @return True if success, False if error
	 */
	public function sendContactEmailRouter($id, $message, $all, $user, $isSiteAdmin, $JobId = false) {
		if (Configure::read('MISP.background_jobs')) {
			$job = ClassRegistry::init('Job');
			$job->create();
			$data = array(
					'worker' => 'email',
					'job_type' => 'contact_alert',
					'job_input' => 'To entire org: ' . $all,
					'status' => 0,
					'retries' => 0,
					'org' => $user['org'],
					'message' => 'Contacting.',
			);
			$job->save($data);
			$jobId = $job->id;
			$process_id = CakeResque::enqueue(
					'email',
					'EventShell',
					array('contactemail', $id, $message, $all, $user['id'], $isSiteAdmin, $jobId)
			);
			$job->saveField('process_id', $process_id);
			return true;
		} else {
			$userMod['User'] = $user;
			$result = $this->sendContactEmail($id, $message, $all, $userMod, $isSiteAdmin);
			return $result;
		}
	}
	
	public function generateLocked() {
		$this->User = ClassRegistry::init('User');
		$this->User->recursive = -1;
		$localOrgs = array();
		$conditions = array();
		//$orgs = $this->User->getOrgs();
		$orgs = $this->User->find('all', array('fields' => array('DISTINCT org')));
		foreach ($orgs as $k => $org) {
			$orgs[$k]['User']['count'] = $this->User->getOrgMemberCount($orgs[$k]['User']['org']);
			if ($orgs[$k]['User']['count'] > 1) {
				$localOrgs[] = $orgs[$k]['User']['org'];
				$conditions['AND'][] = array('orgc !=' => $orgs[$k]['User']['org']);
			} else if ($orgs[$k]['User']['count'] == 1) {
				// If we only have a single user for an org, check if that user is a sync user. If not, then it is a valid local org and the events created by him/her should be unlocked.
				$this->User->recursive = 1;
				$user = ($this->User->find('first', array(
						'fields' => array('id', 'role_id'),
						'conditions' => array('org' => $org['User']['org']),
						'contain' => array('Role' => array(
								'fields' => array('id', 'perm_sync'),
						))
				)));
				if (!$user['Role']['perm_sync']) {
					$conditions['AND'][] = array('orgc !=' => $orgs[$k]['User']['org']);
				}
			}
		}
		// Don't lock stuff that's already locked
		$conditions['AND'][] = array('locked !=' => true);
		$this->recursive = -1;
		$toBeUpdated = $this->find('count', array(
				'conditions' => $conditions
		));
		$this->updateAll(
				array('Event.locked' => 1),
				$conditions
		);
		return $toBeUpdated;
	}
	
	public function reportValidationIssuesEvents() {
		$this->Behaviors->detach('Regexp');
		// get all events..
		$events = $this->find('all', array('recursive' => -1));
		// for all events..
		$result = array();
		$i = 0;
		foreach ($events as $k => $event) {
			$this->set($event);
			if ($this->validates()) {
				// validates
			} else {
				$errors = $this->validationErrors;
		
				$result[$i]['id'] = $event['Event']['id'];
				// print_r
				$result[$i]['error'] = $errors;
				$result[$i]['details'] = $event;
				$i++;
			}
		}
		return array($result, $k);
	}
	
	public function generateThreatLevelFromRisk() {
		$risk = array('Undefined' => 4, 'Low' => 3, 'Medium' => 2, 'High' => 1);
		$events = $this->find('all', array('recursive' => -1));
		foreach ($events as $k => $event) {
			if ($event['Event']['threat_level_id'] == 0 && isset($event['Event']['risk'])) {
				$event['Event']['threat_level_id'] = $risk[$event['Event']['risk']];
				$this->save($event);
			}
		}
		return $k;
	}
	
	// check two version strings. If version 1 is older than 2, return -1, if they are the same return 0, if version 2 is older return 1
	public function compareVersions($version1, $version2) {
		$version1Array = explode('.', $version1);
		$version2Array = explode('.', $version2);
	
		if ($version1Array[0] != $version2Array[0]) {
			if ($version1Array[0] > $version2Array[0]) return 1;
			else return -1;
		}
		if ($version1Array[1] != $version2Array[1]) {
			if ($version1Array[1] > $version2Array[1]) return 1;
			else return -1;
		}
		if ($version1Array[2] != $version2Array[2]) {
			if ($version1Array[2] > $version2Array[2]) return 1;
			else return -1;
		}
	}
	
	// main dispatch method for updating an incoming xmlArray - pass xmlArray to all of the appropriate transformation methods to make all the changes necessary to save the imported event
	public function updateXMLArray($xmlArray, $response = true) {
		if (isset($xmlArray['xml_version']) && $response) {
			$xmlArray['response']['xml_version'] = $xmlArray['xml_version'];
			unset($xmlArray['xml_version']);
		}
		
		if (!$response) {
			$temp = $xmlArray;
			$xmlArray = array();
			$xmlArray['response'] = $temp;
		}
		// if a version is set, it must be at least 2.2.0 - check the version and save the result of the comparison
		if (isset($xmlArray['response']['xml_version'])) $version = $this->compareVersions($xmlArray['response']['xml_version'], $this->mispVersion);
		// if no version is set, set the version to older (-1) manually
		else $version = -1;
		// same version, proceed normally
		if ($version == 0) {
			unset ($xmlArray['response']['xml_version']);
			if ($response) return $xmlArray;
			else return $xmlArray['response'];
		}

		// The xml is from an instance that is newer than the local instance, let the user know that the admin needs to upgrade before it could be imported
		if ($version == 1) throw new Exception('This XML file is from a MISP instance that is newer than the current instance. Please contact your administrator about upgrading this instance.');

		// if the xml contains an event or events from an older MISP instance, let's try to upgrade it!
		// Let's manually set the version to something below 2.2.0 if there is no version set in the xml		
		if (!isset($xmlArray['response']['xml_version'])) $xmlArray['response']['xml_version'] = '2.1.0'; 
		
		// Upgrade from versions below 2.2.0 will need to replace the risk field with threat level id
		if ($this->compareVersions($xmlArray['response']['xml_version'], '2.2.0') < 0) {
			if ($response) $xmlArray['response'] = $this->__updateXMLArray220($xmlArray['response']);
			else $xmlArray = $this->__updateXMLArray220($xmlArray);
		}
		
		unset ($xmlArray['response']['xml_version']);
		if ($response) return $xmlArray;
		else return $xmlArray['response'];
	}

	// replaces the old risk value with the new threat level id
	private function __updateXMLArray220($xmlArray) {
		$risk = array('Undefined' => 4, 'Low' => 3, 'Medium' => 2, 'High' => 1);
		if (isset($xmlArray['Event'][0])) {
			foreach ($xmlArray['Event'] as &$event) {
				if (!isset($event['threat_level_id'])) {
					$event['threat_level_id'] = $risk[$event['risk']];
				}
			}
		} else {
			if (!isset($xmlArray['Event']['threat_level_id']) && isset($xmlArray['Event']['risk'])) {
				$xmlArray['Event']['threat_level_id'] = $risk[$xmlArray['Event']['risk']];
			}
		}
		return $xmlArray;
	}
	
	public function checkIfNewer($incomingEvent) {
		$localEvent = $this->find('first', array('conditions' => array('uuid' => $incomingEvent['uuid']), 'recursive' => -1));
		if (empty($localEvent) || $incomingEvent['timestamp'] > $localEvent['Event']['timestamp']) return true;
		return false;
	}
	
	public function stix($id, $tags, $attachments, $org, $isSiteAdmin, $returnType, $from = false, $to = false, $last = false) {
		$eventIDs = $this->Attribute->dissectArgs($id);
		$tagIDs = $this->Attribute->dissectArgs($tags);
		$idList = $this->getAccessibleEventIds($eventIDs[0], $eventIDs[1], $tagIDs[0], $tagIDs[1]);
		$events = $this->fetchEvent(null, $idList, $org, $isSiteAdmin, false, false, $from, $to, $last);
		// If a second argument is passed (and it is either "yes", "true", or 1) base64 encode all of the attachments
		if ($attachments == "yes" || $attachments == "true" || $attachments == 1) {
			foreach ($events as &$event) {
				foreach ($event['Attribute'] as &$attribute) {
					if ($this->Attribute->typeIsAttachment($attribute['type'])) {
						$encodedFile = $this->Attribute->base64EncodeAttachment($attribute);
						$attribute['data'] = $encodedFile;
					}
				}
			}
		}
                if (Configure::read('MISP.tagging')) {
			foreach ($events as &$event) {
				$event['Tag'] = $this->EventTag->Tag->findEventTags($event['Event']['id']);
                        }
                }
		// generate a randomised filename for the temporary file that will be passed to the python script
		$randomFileName = $this->generateRandomFileName();
		$tempFile = new File (APP . "files" . DS . "scripts" . DS . "tmp" . DS . $randomFileName, true, 0644);
		
		// save the json_encoded event(s) to the temporary file
		$result = $tempFile->write(json_encode($events));
		$scriptFile = APP . "files" . DS . "scripts" . DS . "misp2stix.py";

		// Execute the python script and point it to the temporary filename
		$result = shell_exec('python ' . $scriptFile . ' ' . $randomFileName . ' ' . $returnType . ' ' . Configure::read('MISP.baseurl') . ' "' . Configure::read('MISP.org') . '"');

		// The result of the script will be a returned JSON object with 2 variables: success (boolean) and message
		// If success = 1 then the temporary output file was successfully written, otherwise an error message is passed along
		$decoded = json_decode($result);
		$result = array();
		$result['success'] = $decoded->success;
		$result['message'] = $decoded->message;
	
		if ($result['success'] == 1) {
			$file = new File(APP . "files" . DS . "scripts" . DS . "tmp" . DS . $randomFileName . ".out");
			$result['data'] = $file->read();
		}
		$tempFile->delete();
		$file = new File(APP . "files" . DS . "scripts" . DS . "tmp" . DS . $randomFileName . ".out");
		$file->delete();
 		return $result;
	}
	
	public function getAccessibleEventIds($include, $exclude, $includedTags, $excludedTags) {
		$conditions = array();
		
		// get all of the event IDs based on include / exclude
		if (!empty($include)) $conditions['OR'] = array('id' => $include);
		if (!empty($exclude)) $conditions['NOT'] = array('id' => $exclude);
		$events = $this->find('all', array(
			'recursive' => -1,
			'fields' => array('id', 'org', 'orgc', 'distribution'),
			'conditions' => $conditions
		));
		$ids = array();
		foreach ($events as $event) {
			$ids[] = $event['Event']['id'];
		}
		// get all of the event IDs based on includedTags / excludedTags
		if (!empty($includedTags) || !empty($excludedTags)) {
			$eventIDsFromTags = $this->EventTag->getEventIDsFromTags($includedTags, $excludedTags);
			// get the intersect of the two 
			$ids = array_intersect($ids, $eventIDsFromTags);
		}
		return $ids;
	}
	
	public function generateRandomFileName() {
		$length = 12;
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charLen = strlen($characters) - 1;
		$fn = '';
		for ($p = 0; $p < $length; $p++) {
			$fn .= $characters[rand(0, $charLen)];
		}
		return $fn;
	}
	
	// expects a date string in the YYYY-MM-DD format
	// returns the passed string or false if the format is invalid
	// based on the fix provided by stevengoosensB
	public function dateFieldCheck($date) {
		// regex check for from / to field by stevengoossensB
		return (preg_match('/^[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])$/', $date)) ? $date : false;
	}
	
	//
	public function resolveTimeDelta($delta) {
		$multiplierArray = array('d' => 86400, 'h' => 3600, 'm' => 60);
		$multiplier = $multiplierArray['d'];
		$lastChar = strtolower(substr($delta, -1));
		if (!is_numeric($lastChar) && array_key_exists($lastChar, $multiplierArray)) {
			$multiplier = $multiplierArray[$lastChar];
			$delta = substr($delta, 0, -1);
		}
		if (!is_numeric($delta)) return false;
		return time() - ($delta * $multiplier); 
	}
}

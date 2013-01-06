<?php
namespace PartKeepr\Printing;

use PartKeepr\PartKeepr,
	PartKeepr\Printing\Exceptions\InvalidArgumentException,
	PartKeepr\Printing\Exceptions\RendererNotFoundException,
	PartKeepr\Printing\PageBasicLayout\PageBasicLayoutManager,
	PartKeepr\Printing\PDFLabelRenderer,
	PartKeepr\Printing\PrintingJobConfiguration\PrintingJobConfigurationManager,
	PartKeepr\Service\Service,
	PartKeepr\StorageLocation\StorageLocation,
	PartKeepr\UploadedFile\TempUploadedFile,
	PartKeepr\Util\Configuration as PartKeeprConfiguration;

/**
 * This service is the entry point for our printing/exporting
 * service.
 */
class PrintingService extends Service {
	/**
	 * This array contains all object types which can be used to
	 * for printing. Only these object types will be accepted by the
	 * generatePrintout method to be able to restrict access to the
	 * database.
	 * The value is used to display the selection to the user.
	 */
	private $availableObjectTypes = array(
			'PartKeepr\StorageLocation\StorageLocation' => 'StorageLocation',
			'PartKeepr\Part\Part' => 'Part'
			);
	
	/**
	 * Prints the selected storage locations to a dedicated file
	 * and returns the url to this file.
	 */
	public function startExport () {
		$this->requireParameter("ids");
		$this->requireParameter("configuration");
		$this->requireParameter("objectType");
		
		$ids = explode(',',$this->getParameter("ids"));
		$configurationId = $this->getParameter("configuration");
		$objectType = $this->getParameter("objectType");
		
		// check object type for valid object types for security reasons.
		// See Select query below and be aware of SQL injection!
		if ( !array_key_exists($objectType, $this->availableObjectTypes) ){
			throw new RendererNotFoundException("Object type is forbidden!", $objectType, array_keys($this->availableObjectTypes));
		}
		
		$configuration = PrintingJobConfigurationManager::getInstance()->getObjectById( $configurationId );
		if ($configuration->getPageLayout() == null ){
			throw new InvalidArgumentException( PartKeepr::i18n('Page Layout is emtpy!'));
		}
		
		$query = PartKeepr::getEM()->createQuery("SELECT s FROM $objectType s WHERE s.id IN (?1)");
		$query->setParameter(1,$ids);
		$dataToRender = $query->getResult();
		
		$cfgText = trim($configuration->getRendererConfiguration());
		$rendererConfig = json_decode($cfgText, true);
		if ($rendererConfig===null){
			if (strlen($cfgText) == 0 ){
				$rendererConfig = array();
			}
			else{
				throw new InvalidArgumentException( PartKeepr::i18n('Extended rendering configuration contains an error!'));
			}
		}
		
		$renderer = RendererFactoryRegistry::getInstance()->getRendererFactory( $configuration->getExportRenderer())
						->createInstance( $configuration->getPageLayout(), $rendererConfig );

		$renderer->passRenderingData($dataToRender);
		
		$tempFile = tempnam("/tmp", "PWC");
		$renderer->storeResult( $tempFile );
		
		$tmpFile = new TempUploadedFile();
		$tmpFile->replace($tempFile);
		$tmpFile->setOriginalFilename("generatedFile.".$renderer->getSuggestedExtension());
		
		PartKeepr::getEM()->persist($tmpFile);
		PartKeepr::getEM()->flush();

		return array("fileid" => $tmpFile->getId() );
	}
	
	/**
	 * This service method will return all available renderers for
	 * the given data objecttype to render.
	 */
	public function getAvailableRenderer() {
		$objectType = $this->getParameter("objectType", null);
		
		// Fail early for this type of request!
		if ( $objectType!==null && !array_key_exists($objectType, $this->availableObjectTypes) ){
			throw new RendererNotFoundException("Object type is forbidden!", $objectType, array_keys($this->availableObjectTypes) );
		}
			
		$data = array();
		$renderers = $objectType === null 
			? RendererFactoryRegistry::getInstance()->getRendererFactory( null )
			: RendererFactoryRegistry::getInstance()->getRendererFactoryForRenderData( $objectType );
		
		foreach ( $renderers as $renderer ){
			$data[] = array("id" => $renderer->getCreatedClassname(), 
					"name" => $renderer->getName() );
		}
		
		return array("data" => $data );
	}
	
	public function getAvailableTypes() {
		$data = array();
		foreach ($this->availableObjectTypes as $type => $userType){
			$data[] = array("id" => $type
					,"name" => $userType );
		}
		
		return array("data" => $data );
	}
	
}
<?php

return [

  'countries' => [
    'Pakistan' => 'Pakistan',
    'Afghanistan' => 'Afghanistan',
    'Bangladesh' => 'Bangladesh',
    'China' => 'China',
    'India' => 'India',
    'Iran' => 'Iran',
    'Malaysia' => 'Malaysia',
    'Saudi Arabia' => 'Saudi Arabia',
    'Turkey' => 'Turkey',
    'United Arab Emirates' => 'United Arab Emirates',
    'United Kingdom' => 'United Kingdom',
    'United States' => 'United States',
  ],

  'mobile_categories' => [
    'primary',
    'whatsapp',
    'additional',
  ],

  'supplier_statuses' => [
    'active' => 'Active',
    'inactive' => 'Inactive',
  ],

  /*
   * Default scope for operational supplier dropdowns (Purchase module, etc.).
   * Use: Supplier::operational()->orderBy('business_name')
   */
  'operational_supplier_scope' => 'operational',

  'supplier_documents_disk' => 'local',

  'supplier_documents_directory' => 'contacts/suppliers/documents',

  'supplier_document_types' => [
    'image/jpeg',
    'image/png',
    'image/webp',
  ],

];

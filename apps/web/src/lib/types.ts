export interface Region {
  id: string;
  name: string;
  code: string | null;
  created_at: string;
  updated_at: string;
}

export interface Entity {
  id: string;
  region_id: string;
  name: string;
  code: string | null;
  created_at: string;
  updated_at: string;
  regions?: { id: string; name: string; code: string | null };
}

export interface Project {
  id: string;
  entity_id: string;
  name: string;
  code: string | null;
  created_at: string;
  updated_at: string;
  entities?: { id: string; name: string; code: string | null; region_id: string };
}

export interface CounterpartyContact {
  id: string;
  counterparty_id: string;
  name: string;
  email: string | null;
  role: string | null;
  is_signer: boolean;
  created_at: string;
  updated_at: string;
}

export interface Counterparty {
  id: string;
  legal_name: string;
  registration_number: string | null;
  address: string | null;
  jurisdiction: string | null;
  status: 'Active' | 'Suspended' | 'Blacklisted';
  status_reason: string | null;
  supporting_document_ref: string | null;
  preferred_language: string;
  created_at: string;
  updated_at: string;
  counterparty_contacts?: CounterpartyContact[];
}

export interface Contract {
  id: string;
  region_id: string;
  entity_id: string;
  project_id: string;
  counterparty_id: string;
  contract_type: 'Commercial' | 'Merchant';
  title: string | null;
  workflow_state: string;
  signing_status: string | null;
  storage_path: string | null;
  file_name: string | null;
  file_version: number;
  created_at: string;
  updated_at: string;
  created_by: string | null;
  updated_by: string | null;
  regions?: { id: string; name: string };
  entities?: { id: string; name: string };
  projects?: { id: string; name: string };
  counterparties?: { id: string; legal_name: string; status: string };
}

import os
from contextlib import contextmanager
from datetime import datetime
from io import BytesIO
from typing import Any
from uuid import uuid4

import pytest
from fastapi.testclient import TestClient

# Set required env before importing app
os.environ.setdefault("SUPABASE_URL", "https://test.supabase.co")
os.environ.setdefault("SUPABASE_SERVICE_ROLE_KEY", "test-key")
os.environ.setdefault("JWT_SECRET", "test-secret")

from app.main import app
from app.deps import get_supabase
from app.auth.dependencies import get_current_user
from app.auth.models import CurrentUser


class MockResponse:
    def __init__(self, data=None, count=None, error=None):
        self.data = data or []
        self.count = count
        self.error = error


class MockQuery:
    def __init__(self, supabase, table: str):
        self.supabase = supabase
        self.table = table
        self._filters = []
        self._order = None
        self._range = None
        self._select = "*"
        self._count = None
        self._op = "select"
        self._payload = None
        self._text_search = None
        self._single = False

    def select(self, columns="*", count=None):
        self._select = columns
        self._count = count
        return self

    def insert(self, payload):
        self._op = "insert"
        self._payload = payload
        return self

    def update(self, payload):
        self._op = "update"
        self._payload = payload
        return self

    def delete(self):
        self._op = "delete"
        return self

    def single(self):
        self._single = True
        return self

    def is_(self, column, value):
        def _match(row):
            current = self._get_value(row, column)
            if value in ("null", None):
                return current is None
            return current is not None

        self._filters.append(_match)
        return self

    def eq(self, column, value):
        self._filters.append(lambda row: self._get_value(row, column) == value)
        return self

    def ilike(self, column, pattern):
        needle = pattern.strip("%").lower()
        self._filters.append(lambda row: needle in (self._get_value(row, column) or "").lower())
        return self

    def gte(self, column, value):
        self._filters.append(lambda row: self._get_value(row, column) >= value)
        return self

    def lte(self, column, value):
        self._filters.append(lambda row: self._get_value(row, column) <= value)
        return self

    def or_(self, _query):
        return self

    def text_search(self, _column, query, options=None):
        self._text_search = (query or "").lower()
        return self

    def order(self, column, desc=False):
        self._order = (column, desc)
        return self

    def range(self, start, end):
        self._range = (start, end)
        return self

    def limit(self, n):
        self._range = (0, n - 1)
        return self

    def execute(self):
        table_data = self.supabase.data.get(self.table, [])
        if self._op == "insert":
            rows = self._payload if isinstance(self._payload, list) else [self._payload]
            inserted = []
            for row in rows:
                new_row = dict(row)
                new_row.setdefault("id", str(uuid4()))
                table_data.append(new_row)
                inserted.append(new_row)
            self.supabase.data[self.table] = table_data
            return MockResponse(inserted, count=len(inserted))

        if self._op == "update":
            updated = []
            for row in table_data:
                if all(f(row) for f in self._filters):
                    row.update(self._payload or {})
                    updated.append(dict(row))
            return MockResponse(updated, count=len(updated))

        if self._op == "delete":
            remaining = []
            deleted = []
            for row in table_data:
                if all(f(row) for f in self._filters):
                    deleted.append(dict(row))
                else:
                    remaining.append(row)
            self.supabase.data[self.table] = remaining
            return MockResponse(deleted, count=len(deleted))

        rows = [dict(r) for r in table_data]
        if self.table == "entities" and "regions(" in str(self._select):
            for row in rows:
                region = next((r for r in self.supabase.data["regions"] if r["id"] == row["region_id"]), None)
                row["regions"] = region and {k: region[k] for k in ("id", "name", "code")}
        if self.table == "projects" and "entities(" in str(self._select):
            for row in rows:
                ent = next((e for e in self.supabase.data["entities"] if e["id"] == row["entity_id"]), None)
                row["entities"] = ent and {k: ent[k] for k in ("id", "name", "code", "region_id")}
        if self.table == "contracts":
            if "regions(" in str(self._select):
                for row in rows:
                    reg = next((r for r in self.supabase.data["regions"] if r["id"] == row["region_id"]), None)
                    ent = next((e for e in self.supabase.data["entities"] if e["id"] == row["entity_id"]), None)
                    proj = next((p for p in self.supabase.data["projects"] if p["id"] == row["project_id"]), None)
                    cp = next((c for c in self.supabase.data["counterparties"] if c["id"] == row["counterparty_id"]), None)
                    row["regions"] = reg and {k: reg[k] for k in ("id", "name")}
                    row["entities"] = ent and {k: ent[k] for k in ("id", "name")}
                    row["projects"] = proj and {k: proj[k] for k in ("id", "name")}
                    row["counterparties"] = cp and {k: cp[k] for k in ("id", "legal_name", "status")}
        if self.table == "counterparties" and "counterparty_contacts" in str(self._select):
            for row in rows:
                contacts = [
                    c for c in self.supabase.data["counterparty_contacts"] if c["counterparty_id"] == row["id"]
                ]
                row["counterparty_contacts"] = contacts
        if self.table == "contract_key_dates" and "contracts(" in str(self._select):
            for row in rows:
                contract = next((c for c in self.supabase.data["contracts"] if c["id"] == row["contract_id"]), None)
                row["contracts"] = contract and {
                    k: contract.get(k) for k in ("id", "title", "workflow_state", "entity_id", "region_id")
                }
        if self.table == "reminders" and "contracts(" in str(self._select):
            for row in rows:
                contract = next((c for c in self.supabase.data["contracts"] if c["id"] == row["contract_id"]), None)
                row["contracts"] = contract and {k: contract.get(k) for k in ("id", "title")}
        if self.table == "escalation_events":
            for row in rows:
                if "contracts(" in str(self._select):
                    contract = next((c for c in self.supabase.data["contracts"] if c["id"] == row["contract_id"]), None)
                    row["contracts"] = contract and {k: contract.get(k) for k in ("id", "title", "workflow_state")}
                if "workflow_instances(" in str(self._select):
                    instance = next(
                        (i for i in self.supabase.data["workflow_instances"] if i["id"] == row["workflow_instance_id"]),
                        None,
                    )
                    row["workflow_instances"] = instance and {k: instance.get(k) for k in ("id", "current_stage")}

        if self._text_search:
            rows = [r for r in rows if self._text_search in (r.get("title") or "").lower()]
        for f in self._filters:
            rows = [r for r in rows if f(r)]
        if self._order:
            key, desc = self._order

            def _sort_value(row):
                value = self._get_value(row, key)
                if isinstance(value, bool):
                    return int(value)
                if value is None:
                    return ""
                try:
                    from datetime import date, datetime

                    if isinstance(value, (date, datetime)):
                        return value.isoformat()
                except Exception:
                    pass
                return value

            rows.sort(key=_sort_value, reverse=desc)

        count = len(rows)
        if self._range:
            start, end = self._range
            rows = rows[start : end + 1]
        if self._single:
            return MockResponse(rows[0] if rows else None, count if self._count else None)
        return MockResponse(rows, count if self._count else None)

    @staticmethod
    def _get_value(row, column):
        if "." in column:
            current = row
            for part in column.split("."):
                if not isinstance(current, dict):
                    return None
                current = current.get(part)
            return current
        return row.get(column)


class MockBucket:
    def __init__(self, store: dict[str, Any]):
        self.store = store

    def upload(self, path=None, file=None, file_options=None, **kwargs):
        if path is None:
            path = kwargs.get("path")
        if file is None:
            file = kwargs.get("file")
        self.store[path] = file
        return {"path": path}

    def create_signed_url(self, path, expires_in):
        return {"signedUrl": f"https://storage.test/{path}"}

    def remove(self, paths):
        for p in paths:
            self.store.pop(p, None)
        return {"data": paths}

    def download(self, path):
        return self.store.get(path, b"")


class MockStorage:
    def __init__(self):
        self.files = {}

    def from_(self, _bucket):
        return MockBucket(self.files)


class MockSupabase:
    def __init__(self):
        now = datetime.utcnow().isoformat() + "Z"
        region_id = "11111111-1111-1111-1111-111111111111"
        entity_id = "22222222-2222-2222-2222-222222222222"
        project_id = "33333333-3333-3333-3333-333333333333"
        counterparty_id = "44444444-4444-4444-4444-444444444444"
        counterparty_blocked_id = "55555555-5555-5555-5555-555555555555"
        contact_id = "66666666-6666-6666-6666-666666666666"
        contract_id = "77777777-7777-7777-7777-777777777777"
        contract_executed_id = "88888888-8888-8888-8888-888888888888"
        contract_archived_id = "99999999-9999-9999-9999-999999999999"
        signing_authority_id = "aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"
        audit_id = "bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb"
        wiki_template_id = "cccccccc-cccc-cccc-cccc-cccccccccccc"
        workflow_template_id = "dddddddd-dddd-dddd-dddd-dddddddddddd"
        merchant_contract_id = "eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee"
        analysis_id = "ffffffff-ffff-ffff-ffff-ffffffffffff"
        extracted_field_id = "12121212-1212-1212-1212-121212121212"
        obligation_id = "13131313-1313-1313-1313-131313131313"
        reminder_id = "14141414-1414-1414-1414-141414141414"
        escalation_rule_id = "15151515-1515-1515-1515-151515151515"
        escalation_event_id = "16161616-1616-1616-1616-161616161616"
        notification_id = "17171717-1717-1717-1717-171717171717"
        language_id = "18181818-1818-1818-1818-181818181818"
        workflow_instance_id = "19191919-1919-1919-1919-191919191919"
        key_date_id = "20202020-2020-2020-2020-202020202020"
        self.data = {
            "regions": [
                {"id": region_id, "name": "North", "code": "N", "created_at": now, "updated_at": now},
            ],
            "entities": [
                {
                    "id": entity_id,
                    "region_id": region_id,
                    "name": "Entity One",
                    "code": "E1",
                    "created_at": now,
                    "updated_at": now,
                }
            ],
            "projects": [
                {
                    "id": project_id,
                    "entity_id": entity_id,
                    "name": "Project One",
                    "code": "P1",
                    "created_at": now,
                    "updated_at": now,
                }
            ],
            "counterparties": [
                {
                    "id": counterparty_id,
                    "legal_name": "Acme Corp",
                    "registration_number": "REG123",
                    "address": "123 Road",
                    "jurisdiction": "US",
                    "status": "Active",
                    "status_reason": None,
                    "supporting_document_ref": None,
                    "preferred_language": "en",
                    "created_at": now,
                    "updated_at": now,
                },
                {
                    "id": counterparty_blocked_id,
                    "legal_name": "Blocked Corp",
                    "registration_number": "REG999",
                    "address": None,
                    "jurisdiction": None,
                    "status": "Blacklisted",
                    "status_reason": "Fraud",
                    "supporting_document_ref": None,
                    "preferred_language": "en",
                    "created_at": now,
                    "updated_at": now,
                },
            ],
            "counterparty_contacts": [
                {
                    "id": contact_id,
                    "counterparty_id": counterparty_id,
                    "name": "Alice",
                    "email": "alice@example.com",
                    "role": "Signer",
                    "is_signer": True,
                    "created_at": now,
                    "updated_at": now,
                }
            ],
            "contracts": [
                {
                    "id": contract_id,
                    "region_id": region_id,
                    "entity_id": entity_id,
                    "project_id": project_id,
                    "counterparty_id": counterparty_id,
                    "contract_type": "Commercial",
                    "title": "Contract A",
                    "workflow_state": "draft",
                    "signing_status": None,
                    "storage_path": "region-1/entity-1/project-1/test.pdf",
                    "file_name": "test.pdf",
                    "file_version": 1,
                    "created_at": now,
                    "updated_at": now,
                },
                {
                    "id": contract_executed_id,
                    "region_id": region_id,
                    "entity_id": entity_id,
                    "project_id": project_id,
                    "counterparty_id": counterparty_id,
                    "contract_type": "Commercial",
                    "title": "Executed Contract",
                    "workflow_state": "executed",
                    "signing_status": "completed",
                    "storage_path": "region-1/entity-1/project-1/executed.pdf",
                    "file_name": "executed.pdf",
                    "file_version": 1,
                    "created_at": now,
                    "updated_at": now,
                },
                {
                    "id": contract_archived_id,
                    "region_id": region_id,
                    "entity_id": entity_id,
                    "project_id": project_id,
                    "counterparty_id": counterparty_id,
                    "contract_type": "Commercial",
                    "title": "Archived Contract",
                    "workflow_state": "archived",
                    "signing_status": "completed",
                    "storage_path": "region-1/entity-1/project-1/archived.pdf",
                    "file_name": "archived.pdf",
                    "file_version": 1,
                    "created_at": now,
                    "updated_at": now,
                },
                {
                    "id": merchant_contract_id,
                    "region_id": region_id,
                    "entity_id": entity_id,
                    "project_id": project_id,
                    "counterparty_id": counterparty_id,
                    "contract_type": "Merchant",
                    "title": "Merchant Agreement",
                    "workflow_state": "executed",
                    "signing_status": "completed",
                    "storage_path": "merchant/ma.docx",
                    "file_name": "ma.docx",
                    "file_version": 1,
                    "created_at": now,
                    "updated_at": now,
                },
            ],
            "signing_authority": [
                {
                    "id": signing_authority_id,
                    "entity_id": entity_id,
                    "project_id": None,
                    "user_id": "user-1",
                    "user_email": "legal@example.com",
                    "role_or_name": "Legal",
                    "contract_type_pattern": None,
                    "created_at": now,
                    "updated_at": now,
                }
            ],
            "audit_log": [
                {
                    "id": audit_id,
                    "at": now,
                    "actor_id": "test-1",
                    "actor_email": "test@example.com",
                    "action": "region.create",
                    "resource_type": "region",
                    "resource_id": region_id,
                    "details": {"name": "North"},
                    "ip_address": "127.0.0.1",
                }
            ],
            "wiki_contracts": [
                {
                    "id": wiki_template_id,
                    "name": "Merchant Template",
                    "category": "Merchant",
                    "region_id": region_id,
                    "version": 1,
                    "status": "published",
                    "storage_path": f"wiki-contracts/{wiki_template_id}/template.docx",
                    "file_name": "template.docx",
                    "description": "Base template",
                    "created_by": "test-1",
                    "published_at": now,
                    "created_at": now,
                    "updated_at": now,
                }
            ],
            "workflow_templates": [
                {
                    "id": workflow_template_id,
                    "name": "Standard Workflow",
                    "contract_type": "Commercial",
                    "region_id": region_id,
                    "entity_id": entity_id,
                    "project_id": project_id,
                    "version": 1,
                    "status": "published",
                    "stages": [
                        {
                            "name": "Review",
                            "type": "approval",
                            "owners": ["Legal"],
                            "approvers": ["Legal"],
                            "allowed_transitions": ["Sign"],
                            "required_artifacts": [],
                        },
                        {
                            "name": "Sign",
                            "type": "signing",
                            "owners": ["Legal"],
                            "approvers": ["Legal"],
                            "allowed_transitions": [],
                            "required_artifacts": [],
                        },
                    ],
                    "created_at": now,
                    "updated_at": now,
                }
            ],
            "workflow_instances": [],
            "workflow_stage_actions": [],
            "boldsign_envelopes": [
                {
                    "id": str(uuid4()),
                    "contract_id": contract_id,
                    "status": "sent",
                    "created_at": now,
                }
            ],
            "contract_links": [],
            "contract_key_dates": [
                {
                    "id": key_date_id,
                    "contract_id": contract_id,
                    "date_type": "expiry_date",
                    "date_value": now[:10],
                    "description": "Expiry",
                    "reminder_days": [30],
                    "created_at": now,
                    "updated_at": now,
                }
            ],
            "merchant_agreement_inputs": [],
            "ai_analysis_results": [
                {
                    "id": analysis_id,
                    "contract_id": contract_id,
                    "analysis_type": "summary",
                    "status": "completed",
                    "result": {"summary": "Test summary", "key_parties": []},
                    "token_usage_input": 100,
                    "token_usage_output": 50,
                    "cost_usd": 0.01,
                    "model_used": "test-model",
                    "created_at": now,
                    "updated_at": now,
                }
            ],
            "ai_extracted_fields": [
                {
                    "id": extracted_field_id,
                    "contract_id": contract_id,
                    "analysis_id": analysis_id,
                    "field_name": "term",
                    "field_value": "12 months",
                    "evidence_clause": "Term is 12 months",
                    "confidence": 0.8,
                    "is_verified": False,
                    "created_at": now,
                    "updated_at": now,
                }
            ],
            "obligations_register": [
                {
                    "id": obligation_id,
                    "contract_id": contract_id,
                    "analysis_id": analysis_id,
                    "obligation_type": "sla",
                    "description": "Monthly report",
                    "due_date": now[:10],
                    "recurrence": "monthly",
                    "responsible_party": "Vendor",
                    "status": "active",
                    "created_at": now,
                    "updated_at": now,
                }
            ],
            "reminders": [
                {
                    "id": reminder_id,
                    "contract_id": contract_id,
                    "key_date_id": key_date_id,
                    "reminder_type": "expiry",
                    "lead_days": 30,
                    "channel": "email",
                    "recipient_email": "ops@example.com",
                    "recipient_user_id": None,
                    "last_sent_at": None,
                    "next_due_at": now,
                    "is_active": True,
                    "created_at": now,
                    "updated_at": now,
                }
            ],
            "escalation_rules": [
                {
                    "id": escalation_rule_id,
                    "workflow_template_id": workflow_template_id,
                    "stage_name": "Review",
                    "sla_breach_hours": 1,
                    "tier": 1,
                    "escalate_to_role": "Legal",
                    "escalate_to_user_id": None,
                    "created_at": now,
                    "updated_at": now,
                }
            ],
            "escalation_events": [
                {
                    "id": escalation_event_id,
                    "workflow_instance_id": workflow_instance_id,
                    "rule_id": escalation_rule_id,
                    "contract_id": contract_id,
                    "stage_name": "Review",
                    "tier": 1,
                    "escalated_at": now,
                    "resolved_at": None,
                    "resolved_by": None,
                    "created_at": now,
                }
            ],
            "notifications": [
                {
                    "id": notification_id,
                    "recipient_email": "test@example.com",
                    "channel": "email",
                    "subject": "Test notification",
                    "status": "pending",
                    "sent_at": None,
                    "created_at": now,
                }
            ],
            "contract_languages": [
                {
                    "id": language_id,
                    "contract_id": contract_id,
                    "language_code": "en",
                    "is_primary": True,
                    "storage_path": f"contracts/{contract_id}/languages/en/contract-en.pdf",
                    "file_name": "contract-en.pdf",
                    "created_at": now,
                }
            ],
        }
        self.storage = MockStorage()
        try:
            from docx import Document

            doc = Document()
            doc.add_paragraph("{{vendor_name}}")
            temp_bytes = BytesIO()
            doc.save(temp_bytes)
            self.storage.files[f"wiki-contracts/{wiki_template_id}/template.docx"] = temp_bytes.getvalue()
        except Exception:
            self.storage.files[f"wiki-contracts/{wiki_template_id}/template.docx"] = b""

        self.storage.files["region-1/entity-1/project-1/test.pdf"] = b"contract"
        self.storage.files[f"contracts/{contract_id}/languages/en/contract-en.pdf"] = b"language"

    def table(self, name: str):
        return MockQuery(self, name)


@pytest.fixture
def mock_supabase():
    return MockSupabase()


@pytest.fixture
def test_user() -> CurrentUser:
    return CurrentUser(id="test-1", email="test@example.com", roles=["System Admin"], ip_address="127.0.0.1")


@pytest.fixture
def legal_user() -> CurrentUser:
    return CurrentUser(id="legal-1", email="legal@example.com", roles=["Legal"], ip_address="127.0.0.1")


@pytest.fixture
def authed_client(mock_supabase, test_user):
    app.dependency_overrides[get_supabase] = lambda: mock_supabase
    app.dependency_overrides[get_current_user] = lambda: test_user
    client = TestClient(app)
    yield client
    app.dependency_overrides.clear()


@pytest.fixture
def legal_client(mock_supabase, legal_user):
    app.dependency_overrides[get_supabase] = lambda: mock_supabase
    app.dependency_overrides[get_current_user] = lambda: legal_user
    client = TestClient(app)
    yield client
    app.dependency_overrides.clear()


@pytest.fixture
def unauthed_client():
    return TestClient(app)


@pytest.fixture
def client_context(mock_supabase):
    @contextmanager
    def _ctx(roles: list[str]):
        app.dependency_overrides[get_supabase] = lambda: mock_supabase
        app.dependency_overrides[get_current_user] = lambda: CurrentUser(
            id="ctx-user", email="ctx@example.com", roles=roles, ip_address="127.0.0.1"
        )
        client = TestClient(app)
        try:
            yield client
        finally:
            app.dependency_overrides.clear()

    return _ctx

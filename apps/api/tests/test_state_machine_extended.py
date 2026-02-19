from app.workflows.state_machine import can_actor_act, get_next_stage, validate_template
from app.workflows.schemas import WorkflowStage


def _stage(name, type_="approval", **kwargs):
    return WorkflowStage(name=name, type=type_, **kwargs)


def test_validate_empty_stages():
    errors = validate_template([])
    assert any("at least" in e.lower() for e in errors)


def test_validate_duplicate_stage_names():
    stages = [
        _stage("Review", allowed_transitions=["Review"]),
        _stage("Review", allowed_transitions=[]),
    ]
    errors = validate_template(stages)
    assert any("unique" in e.lower() or "duplicate" in e.lower() for e in errors)


def test_validate_first_stage_is_signing():
    stages = [
        _stage("Sign First", type_="signing", allowed_transitions=["Approve"]),
        _stage("Approve", allowed_transitions=[]),
    ]
    errors = validate_template(stages)
    assert any("signing" in e.lower() and "first" in e.lower() for e in errors)


def test_validate_orphan_stage():
    stages = [
        _stage("Review", allowed_transitions=["Approval"]),
        _stage("Approval", type_="approval", allowed_transitions=["Sign"]),
        _stage("Sign", type_="signing", allowed_transitions=[]),
        _stage("Orphan", allowed_transitions=[]),
    ]
    errors = validate_template(stages)
    assert any("orphan" in e.lower() or "unreachable" in e.lower() for e in errors)


def test_get_next_stage_reject():
    stages = [
        _stage("Draft", type_="draft", allowed_transitions=["Review"]),
        _stage("Review", allowed_transitions=["Sign"]),
        _stage("Sign", type_="signing", allowed_transitions=[]),
    ]
    result = get_next_stage(stages, "Review", "reject")
    assert result == "Draft"


def test_get_next_stage_reject_at_first_stage():
    stages = [
        _stage("Review", allowed_transitions=["Sign"]),
        _stage("Sign", type_="signing", allowed_transitions=[]),
    ]
    result = get_next_stage(stages, "Review", "reject")
    assert result == "Review"


def test_get_next_stage_approve_terminal():
    stages = [
        _stage("Review", allowed_transitions=[]),
    ]
    result = get_next_stage(stages, "Review", "approve")
    assert result is None


def test_get_next_stage_unknown_stage():
    stages = [_stage("Review", allowed_transitions=[])]
    result = get_next_stage(stages, "NonExistent", "approve")
    assert result is None


def test_can_actor_act_by_user_id():
    stage = _stage("Review", owners=["user-123"])

    class Actor:
        id = "user-123"
        email = "test@test.com"
        roles = []

    assert can_actor_act(stage, Actor(), signing_authority=None)


def test_can_actor_act_denied():
    stage = _stage("Review", owners=["user-other"], approvers=["Legal"])

    class Actor:
        id = "user-123"
        email = "test@test.com"
        roles = ["Viewer"]

    assert not can_actor_act(stage, Actor(), signing_authority=None)

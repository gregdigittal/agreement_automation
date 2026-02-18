from app.auth.models import CurrentUser
from app.workflows.schemas import WorkflowStage
from app.workflows.state_machine import can_actor_act, get_next_stage, validate_template


def test_validate_template():
    stages = [
        WorkflowStage(name="Review", type="approval", allowed_transitions=["Sign"]),
        WorkflowStage(name="Sign", type="signing", allowed_transitions=[]),
    ]
    assert validate_template(stages) == []


def test_validate_template_missing_approval():
    stages = [WorkflowStage(name="Sign", type="signing", allowed_transitions=[])]
    errors = validate_template(stages)
    assert any("approval" in e for e in errors)


def test_get_next_stage():
    stages = [
        WorkflowStage(name="Review", type="approval", allowed_transitions=["Sign"]),
        WorkflowStage(name="Sign", type="signing", allowed_transitions=[]),
    ]
    assert get_next_stage(stages, "Review", "approve") == "Sign"


def test_can_actor_act():
    stage = WorkflowStage(name="Review", type="approval", owners=["Legal"], approvers=["Legal"])
    actor = CurrentUser(id="user-1", email="legal@example.com", roles=["Legal"])
    assert can_actor_act(stage, actor, [])

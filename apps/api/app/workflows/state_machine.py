from collections import deque

from app.auth.models import CurrentUser
from app.workflows.schemas import WorkflowStage


def validate_template(stages: list[WorkflowStage]) -> list[str]:
    errors: list[str] = []
    if not stages:
        return ["Workflow must include at least one stage."]

    names = [s.name for s in stages]
    if len(names) != len(set(names)):
        errors.append("Stage names must be unique.")

    if stages[0].type == "signing":
        errors.append("First stage cannot be a signing stage.")

    if not any(s.type == "approval" for s in stages):
        errors.append("Workflow must include at least one approval stage.")

    if not any(s.type == "signing" for s in stages):
        errors.append("Workflow must include at least one signing stage.")

    valid_names = set(names)
    for stage in stages:
        for nxt in stage.allowed_transitions:
            if nxt not in valid_names:
                errors.append(f"Stage '{stage.name}' has invalid transition to '{nxt}'.")

    # Reachability check (no orphan stages)
    graph = {s.name: s.allowed_transitions for s in stages}
    visited = set()
    queue = deque([stages[0].name])
    while queue:
        node = queue.popleft()
        if node in visited:
            continue
        visited.add(node)
        for nxt in graph.get(node, []):
            if nxt not in visited:
                queue.append(nxt)
    orphaned = [s.name for s in stages if s.name not in visited]
    if orphaned:
        errors.append(f"Orphan stages: {', '.join(orphaned)}")

    return errors


def get_next_stage(stages: list[WorkflowStage], current: str, action: str) -> str | None:
    stage = next((s for s in stages if s.name == current), None)
    if not stage:
        return None
    if action == "approve":
        return stage.allowed_transitions[0] if stage.allowed_transitions else None
    if action in ("reject", "rework"):
        stage_names = [s.name for s in stages]
        current_idx = stage_names.index(current) if current in stage_names else 0
        if current_idx > 0:
            return stage_names[current_idx - 1]
        return current
    return None


def can_actor_act(stage: WorkflowStage, actor: CurrentUser, signing_authority: list[dict]) -> bool:
    owners = set(stage.owners or [])
    approvers = set(stage.approvers or [])
    roles = set(actor.roles or [])

    if actor.id in owners or actor.id in approvers:
        return True
    if roles.intersection(owners) or roles.intersection(approvers):
        return True

    if stage.type == "signing" and signing_authority:
        for rule in signing_authority:
            if rule.get("user_id") == actor.id:
                return True
            if rule.get("user_email") and rule.get("user_email") == actor.email:
                return True
            if rule.get("role_or_name") and rule.get("role_or_name") in roles:
                return True
    return False

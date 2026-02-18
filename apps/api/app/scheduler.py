import structlog
from apscheduler.schedulers.asyncio import AsyncIOScheduler

from app.config import settings
from app.deps import get_supabase

logger = structlog.get_logger()
scheduler = AsyncIOScheduler()


async def _run_reminders():
    from app.reminders.service import process_due_reminders

    supabase = get_supabase()
    count = await process_due_reminders(supabase)
    logger.info("scheduler_reminders_done", sent=count)


async def _run_escalation_check():
    from app.escalation.service import check_sla_breaches

    supabase = get_supabase()
    count = await check_sla_breaches(supabase)
    logger.info("scheduler_escalation_done", created=count)


async def _run_notifications():
    from app.notifications.service import send_pending_notifications

    supabase = get_supabase()
    count = await send_pending_notifications(supabase)
    logger.info("scheduler_notifications_done", sent=count)


def start_scheduler():
    if not settings.scheduler_enabled:
        logger.info("scheduler_disabled")
        return

    scheduler.add_job(_run_reminders, "cron", hour=8, minute=0, id="reminders")
    scheduler.add_job(_run_escalation_check, "interval", hours=1, id="escalation")
    scheduler.add_job(_run_notifications, "interval", minutes=5, id="notifications")

    scheduler.start()
    logger.info("scheduler_started", jobs=["reminders", "escalation", "notifications"])


def stop_scheduler():
    if scheduler.running:
        scheduler.shutdown(wait=False)
        logger.info("scheduler_stopped")
import structlog
from apscheduler.schedulers.asyncio import AsyncIOScheduler

from app.config import settings
from app.deps import get_supabase

logger = structlog.get_logger()
scheduler = AsyncIOScheduler()


async def _run_reminders():
    from app.reminders.service import process_due_reminders

    supabase = get_supabase()
    count = await process_due_reminders(supabase)
    logger.info("scheduler_reminders_done", sent=count)


async def _run_escalation_check():
    from app.escalation.service import check_sla_breaches

    supabase = get_supabase()
    count = await check_sla_breaches(supabase)
    logger.info("scheduler_escalation_done", created=count)


async def _run_notifications():
    from app.notifications.service import send_pending_notifications

    supabase = get_supabase()
    count = await send_pending_notifications(supabase)
    logger.info("scheduler_notifications_done", sent=count)


def start_scheduler():
    if not settings.scheduler_enabled:
        logger.info("scheduler_disabled")
        return

    scheduler.add_job(_run_reminders, "cron", hour=8, minute=0, id="reminders")
    scheduler.add_job(_run_escalation_check, "interval", hours=1, id="escalation")
    scheduler.add_job(_run_notifications, "interval", minutes=5, id="notifications")

    scheduler.start()
    logger.info("scheduler_started", jobs=["reminders", "escalation", "notifications"])


def stop_scheduler():
    if scheduler.running:
        scheduler.shutdown(wait=False)
        logger.info("scheduler_stopped")

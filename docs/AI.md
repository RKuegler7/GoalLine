# How GoalLine uses AI

GoalLine uses **Anthropic's Claude API** as a coach. The AI drafts and explains; plain, tested PHP decides everything that counts. It never scores a week, never rules on a dispute, never sets the minimum pace, and never gives medical advice.

Every AI call runs in the background queue (except the optional caption helper), returns structured JSON that's checked against a schema, and has a fallback, so a slow or failed call never leaves anyone stuck. The model is set by `ANTHROPIC_MODEL`. The prompts and the minimum-pace formulas aren't published here: the behavior is described below, and the tests pin it down.

## The AI jobs

### 1. Intake questions
- **When:** right after someone sets a goal (`GenerateIntakeQuestions`).
- **In:** the goal's title, category and deadline.
- **Out:** 5 to 8 questions a coach would ask before planning, each a short answer, choice or number.
- **Checked:** must match the schema, with a sensible number of questions; text is trimmed and capped; anything incomplete or unsafe is dropped (`IntakeQuestions`). Physical goals always get an injury question.
- **Fallback:** built-in questions for the goal's category, used straight away if the call fails or times out.

### 2. Drafting the plan
- **When:** after the intake answers (`GenerateRoadmap`).
- **In:** the goal, deadline, intake answers, the dated GoalWeeks to fill, and a digest of any uploaded study or training plan.
- **Out:** for each GoalWeek, a focus, a milestone and daily tasks, plus habits with clear rules ("Run · 2+ miles" on Mon, Wed, Fri).
- **Checked:** schema, week count and dates, lengths, and no empty weeks. Then **code sets each week's minimum planned points** (`PlanFloor`) and raises any week the AI planned too light, telling the member why.
- **Fallback:** a starter plan for the goal's category (`FallbackPlan`), raised to the minimum pace, with a plain note that the coach couldn't build one this time.
- **Never final on its own:** the plan is a draft until the member accepts it, and every version is kept in plan history.

### 3. Reading an uploaded plan
- **When:** a member uploads a study guide, syllabus or training plan (`ReadPlanMaterial`).
- **In:** the file (PDF or image), read once.
- **Out:** a short digest the plan draft can use.
- **Fallback:** the plan is drafted without it; the member is told the file couldn't be read.

### 4. Refining the plan in the member's own words
- **When:** "Make weeks 4 and 5 lighter, I have finals" (`RefinePlan`).
- **In:** the instruction and only the GoalWeeks that are still allowed to change (not started yet).
- **Out:** a revised version of those weeks and a one-paragraph explanation.
- **Checked:** only editable weeks can change, past check-ins and group or personal tasks are never touched, and no week may drop below its minimum pace (`PlanEditor`). The member sees a week-by-week preview and chooses **Accept** or **Discard**.
- **Fallback:** "Couldn't refine it right now, try again"; the plan stays as it was.

### 5. Checking manual edits, and the weekly coach's tip
- **When:** after a member edits their plan by hand (`ReviewRoadmapChanges`), and at the end of a GoalWeek (`CoachWeekReview`).
- **Out:** a short, encouraging note: whether the edited plan still fits the goal, and one process-focused tip for next week.
- **Checked:** length-capped, plain text.
- **Fallback:** no note, and nothing else changes. Scoring never waits on these.

### 6. Caption suggestion (optional)
- **When:** a member taps "Suggest a caption" on a post (`CaptionWriter`). It's the only AI call made while someone waits, so it has a 20-second limit.
- **In:** the goal, the GoalWeek's focus, and the task checked off (with its result, if it has one).
- **Out:** one short caption the member edits or ignores.
- **Fallback:** "Couldn't suggest one right now. Write your own!"

### 7. Weekly recap narrator
- **When:** the first time a member opens a finished week's recap (`RecapNarrator`, queued as `NarrateRecap`). It's saved once per member and week, and written again only if that week's numbers change (for example, after a dispute re-scores it).
- **In:** only numbers code already computed: check-ins, active days, best day, line points, streak, badges, cheers, and each goal's result and percent of plan. No notes, reflections or intake answers.
- **Out:** two or three warm, process-focused sentences.
- **Checked:** 2 to 3 sentences, a sensible length, and **no number (in digits or words) that code didn't hand it**. So it can't invent a total or do arithmetic on a score.
- **Fallback:** a plain template written by code from the same numbers. The page always says which one you're reading.

## You can see who did what

Each goal page has a **"What the AI did, what code did"** panel, built from that goal's real history: how many intake questions the coach wrote, that it drafted the GoalWeeks, each refinement you approved, and on the code side which weeks were raised to the minimum pace ("We raised GoalWeek 3 to your minimum pace."), how many weeks are locked, and the points scored so far. The About page shows the general version (`AiCodeSplit`, `x-ai-code-split`).

## What the AI never does

- **Score anything.** Points, bands, credited percent, match results, photo finishes, byes and league tables are plain PHP (`GoalWeekResult`, `MatchupOutcome`, `LeagueTable`), with tests for every boundary.
- **Rule on disputes.** People settle disputes: proof, an explanation, a withdrawal, or the league owner's ruling (`Disputes`).
- **Set the minimum pace.** Code does (`PlanFloor`), and it can only make a plan harder.
- **Give medical advice.** Plans are general guidance, physical goals get conservative progression and rest days, and the app says to check with a doctor about health concerns.
- **Write share cards or recaps' numbers.** Weekly result cards are drawn by code from the scoring code's results (`ResultCardData`). The recap narrator only words numbers code gives it, and any answer with a new number is thrown away.

## Cost and safety controls

- **Caps** (`AiBudget`, `config/goalline.php`): requests per member per day, dollars per day and per month, with an email to the admins at 80%. When a cap is reached, AI features pause with a friendly message and the built-in fallbacks take over; everything else keeps working.
- **Timeouts and retries:** every call has a hard timeout (10 minutes for queued planning, 20 seconds for captions) and at most 2 retries.
- **Demo:** Try the demo uses saved sample answers (`DemoCoach`) and never calls the live AI or spends money (`DEMO_LIVE_AI=false`).
- **Privacy:** only what a job needs is sent. Plan summaries shared with a league never include private notes, intake answers or reflections.
- **Keys:** the API key lives only in the server's environment properties, never in the repo.
- **Tests never call the API:** the test suite blocks the real client, and the planner is mocked.

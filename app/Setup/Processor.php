<?php

namespace App\Setup;

use App\Setup\Crud\ReportCrud;

/**
 * Entry point for the complete CRUD surface.
 *
 * Public inheritance combines every module. Controllers use the matching child
 * class, and Processor remains when one object must inherit every operation.
 *
 * Manager → Loader → Crud → FinalProjectCrud → StudyProgramCrud → MembershipCrud
 * → UserCrud → FinalProjectTypeCrud → VisitorCrud → BookGrantCrud → LibraryClearanceCrud
 * → BarcodeCrud → SubmissionCrud → SearchCrud → DestructionCrud → ArchiveCrud
 * → ReportCrud → Processor.
 */
class Processor extends ReportCrud {}

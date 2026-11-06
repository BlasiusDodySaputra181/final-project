<?php

namespace App\Setup;

use App\Setup\Crud\ReportCrud;

/**
 * Titik masuk CRUD lengkap.
 *
 * Public inheritance menggabungkan seluruh modul. Controller memakai kelas anak
 * yang sesuai; Processor tetap ada bila satu objek harus mewarisi semua operasi.
 *
 * Manager → Loader → Crud → FinalProjectCrud → StudyProgramCrud → MembershipCrud
 * → UserCrud → FinalProjectTypeCrud → VisitorCrud → BookGrantCrud → LibraryClearanceCrud
 * → BarcodeCrud → SubmissionCrud → SearchCrud → DestructionCrud → ArchiveCrud
 * → ReportCrud → Processor.
 */
class Processor extends ReportCrud {}

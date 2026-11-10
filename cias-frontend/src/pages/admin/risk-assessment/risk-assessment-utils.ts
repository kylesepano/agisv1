import type {
  AssessmentMatrixScores,
  RiskAssessmentRating,
  RiskRegisterRecord,
} from "./risk-assessment-types";

export function calculateAssessmentMatrixTotal(scores: AssessmentMatrixScores) {
  return Object.values(scores).reduce((sum, value) => sum + value, 0);
}

export function getAssessmentMatrixRating(total: number): RiskAssessmentRating {
  if (total >= 29) {
    return "Critical";
  }

  if (total >= 21) {
    return "High";
  }

  if (total >= 14) {
    return "Medium";
  }

  return "Low";
}

export function getAssessmentPriority(rating: RiskAssessmentRating) {
  if (rating === "Critical" || rating === "High") {
    return "High Priority";
  }

  if (rating === "Medium") {
    return "Moderate Priority";
  }

  return "Low Priority";
}

export function getRiskRating(score: number): RiskAssessmentRating {
  if (score >= 16) {
    return "Critical";
  }

  if (score >= 9) {
    return "High";
  }

  if (score >= 5) {
    return "Medium";
  }

  return "Low";
}

export function formatAssessmentDate(value: string) {
  const date = new Date(`${value}T00:00:00`);

  return new Intl.DateTimeFormat("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  }).format(date);
}

export function getAssessmentSummary(records: RiskRegisterRecord[]) {
  const completed = records.filter((record) => record.status === "Completed").length;
  const highPriority = records.filter(
    (record) => record.rating === "High" || record.rating === "Critical",
  ).length;

  return {
    total: records.length,
    completed,
    highPriority,
  };
}

export function getRiskRegisterOverview(records: RiskRegisterRecord[]) {
  const total = records.length;
  const critical = records.filter((record) => record.rating === "Critical").length;
  const high = records.filter((record) => record.rating === "High").length;
  const medium = records.filter((record) => record.rating === "Medium").length;
  const low = records.filter((record) => record.rating === "Low").length;
  const averageScore =
    total === 0
      ? 0
      : records.reduce((sum, record) => sum + record.score, 0) / total;

  return {
    total,
    critical,
    high,
    medium,
    low,
    averageScore,
  };
}

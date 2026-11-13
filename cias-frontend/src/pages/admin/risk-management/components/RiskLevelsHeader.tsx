import { Plus } from "lucide-react";
import { Button } from "../../../../components/ui/button";
import { Heading, Paragraph } from "../../../../components/ui/typography";

type RiskLevelsHeaderProps = {
  onAddRiskLevel?: () => void;
};

export function RiskLevelsHeader({ onAddRiskLevel }: RiskLevelsHeaderProps) {
  return (
    <header className="flex items-start justify-between gap-3 max-[700px]:flex-col">
      <div>
        <Heading as={2} className="text-slate-950">
          Risk Levels
        </Heading>
        <Paragraph className="mt-0.5 text-slate-600">
          Manage risk level classifications and score ranges used in risk
          assessment.
        </Paragraph>
      </div>

      <Button
        className="h-9 rounded-lg bg-[#2563eb] px-3.5 text-white hover:bg-[#1d4ed8]"
        onClick={onAddRiskLevel}
      >
        <Plus size={14} />
        Add Risk Level
      </Button>
    </header>
  );
}

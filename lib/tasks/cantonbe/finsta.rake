# encoding: UTF-8

namespace :cantonbe do
  namespace :finsta do
    namespace :generate_json do
      desc "creates funktionale gliederung json files (optionally pass bfs id, defaults to all)"
      task :funktionale_gliederung, [:bfs_no] do |t, args|
        args.with_defaults(:bfs_no => '*')
        files = Dir.glob("source/finstabe/bfh/funktionale_gliederung/FINSTA_NachFunktionalerGliederung_#{args.bfs_no}.csv")
        puts "start processing #{files.length} files (bfs no: #{args.bfs_no})"
        files.each do |file_path|
          budget = OpenBudget::Budget.new

          budget.from_finstabe_bfh_csv file_path
          # p nodes.nodes.to_s
          if budget.meta.present? && budget.meta[:bfs_no].present?
            File.open("data/finstabe/funktionale_gliederung/#{budget.meta[:bfs_no]}.json", 'wb') do |file|
              file.write budget.to_json
            end
            puts "#{budget.meta[:bfs_no]} done"
          else
            puts "#{file_path} meta missing"
          end
        end
      end
    end
  end
end
